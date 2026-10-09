<?php

namespace app\service;

use app\model\AiTask;
use app\model\Novel;
use app\support\RedisClient;

/**
 * AI 任务队列（Redis List + 独立 Worker 消费）
 */
class AiTaskService
{
    public const QUEUE_KEY = 'ai:queue';
    public const STREAM_PREFIX = 'ai:stream:';
    public const STREAM_TTL = 3600;

    /**
     * 允许的任务类型
     */
    public const TYPES = [
        'generate_setting',
        'generate_outline',
        'generate_chapter',
        'continue_chapter',
        'regenerate_chapter',
        'generate_summary',
        'update_memory',
        'consistency_check',
    ];

    /**
     * 创建任务并入队（同一小说同时只允许一个进行中任务）
     */
    public static function create(string $type, int $novelId, array $params = []): AiTask
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \RuntimeException('未知任务类型: ' . $type);
        }
        if (!Novel::where('id', $novelId)->exists()) {
            throw new \RuntimeException('小说不存在');
        }
        $running = AiTask::where('ref_id', $novelId)
            ->where('ref_type', 'novel')
            ->whereIn('status', ['pending', 'running'])
            ->first();
        if ($running) {
            throw new \RuntimeException("该小说已有进行中的任务（{$running->task_type}），请等待完成");
        }
        return self::enqueue($type, $novelId, $params);
    }

    /**
     * 内部自动入队（章节完成后自动安排审校等场景）：
     * 不检查同小说进行中任务——调用方保证当前任务即将结束、队列串行消费
     */
    public static function enqueue(string $type, int $novelId, array $params = []): AiTask
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \RuntimeException('未知任务类型: ' . $type);
        }

        $task = new AiTask();
        $task->task_type = $type;
        $task->ref_id = $novelId;
        $task->ref_type = 'novel';
        $task->params = $params;
        $task->status = 'pending';
        $task->error_message = '';
        $task->created_at = now();
        $task->updated_at = now();
        $task->save();

        $redis = RedisClient::connect();
        $redis->lpush(self::QUEUE_KEY, (string)$task->id);
        $redis->disconnect();

        self::publish($task->id, ['type' => 'status', 'status' => 'pending', 'message' => '任务已入队']);
        return $task;
    }

    /**
     * 推送流事件（前端 SSE 订阅消费）
     */
    public static function publish(int $taskId, array $event): void
    {
        try {
            $redis = RedisClient::connect();
            $redis->lpush(self::STREAM_PREFIX . $taskId, json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $redis->expire(self::STREAM_PREFIX . $taskId, self::STREAM_TTL);
            $redis->disconnect();
        } catch (\Throwable) {
            // 发布失败不影响任务本身
        }
    }

    /**
     * 结束流（写完成标记 + 推送 done 事件）
     */
    public static function finish(int $taskId, string $status, string $error = ''): void
    {
        self::publish($taskId, [
            'type' => 'done',
            'status' => $status,
            'error_message' => mb_substr($error, 0, 500),
        ]);
        try {
            $redis = RedisClient::connect();
            $redis->setex(self::STREAM_PREFIX . $taskId . ':done', self::STREAM_TTL, $status);
            $redis->disconnect();
        } catch (\Throwable) {
        }
    }

    /**
     * 由 Worker 消费：执行一个任务
     */
    public static function execute(int $taskId): void
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            return;
        }
        if ($task->status === 'success') {
            return;
        }

        $task->status = 'running';
        $task->error_message = '';
        $task->updated_at = now();
        $task->save();
        self::publish($taskId, ['type' => 'status', 'status' => 'running', 'message' => '任务开始执行']);

        try {
            $novel = Novel::find($task->ref_id);
            if (!$novel) {
                throw new \RuntimeException('小说不存在');
            }

            $ai = new AiService();
            $onStage = function (string $stage, string $message = '') use ($taskId) {
                self::publish($taskId, ['type' => 'status', 'stage' => $stage, 'message' => $message]);
            };
            $onDelta = function (string $delta) use ($taskId) {
                self::publish($taskId, ['type' => 'chunk', 'content' => $delta]);
            };

            switch ($task->task_type) {
                case 'generate_setting':
                    $ai->generateSetting($novel, $onStage);
                    break;
                case 'generate_outline':
                    $ai->generateOutline($novel, $onStage);
                    break;
                case 'generate_chapter':
                case 'continue_chapter':
                case 'regenerate_chapter':
                    $ai->generateChapter($novel, $task, $onDelta, $onStage);
                    break;
                case 'generate_summary':
                    $ai->generateSummaryForChapter($novel, $task, $onStage);
                    break;
                case 'update_memory':
                    $ai->updateMemory($novel);
                    $onStage('done', '小说记忆更新完成');
                    break;
                case 'consistency_check':
                    $ai->checkConsistency($novel, $task, $onStage);
                    break;
                default:
                    throw new \RuntimeException('未知任务类型: ' . $task->task_type);
            }

            $task->status = 'success';
            $task->error_message = '';
            $task->updated_at = now();
            $task->save();
            self::finish($taskId, 'success');
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $task->status = 'failed';
            $task->error_message = mb_substr($message, 0, 900);
            $task->updated_at = now();
            $task->save();
            self::finish($taskId, 'failed', $message);
        }
    }

    /**
     * 失败任务重试
     */
    public static function retry(int $taskId): AiTask
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }
        if (!in_array($task->status, ['failed'], true)) {
            throw new \RuntimeException('只有失败的任务才能重试');
        }
        // 同一小说有进行中任务则不允许
        $running = AiTask::where('ref_id', $task->ref_id)
            ->where('ref_type', $task->ref_type)
            ->whereIn('status', ['pending', 'running'])
            ->first();
        if ($running) {
            throw new \RuntimeException('该小说已有进行中的任务，请等待完成');
        }

        $task->status = 'pending';
        $task->error_message = '';
        $task->updated_at = now();
        $task->save();

        $redis = RedisClient::connect();
        $redis->lpush(self::QUEUE_KEY, (string)$task->id);
        $redis->disconnect();

        self::publish($taskId, ['type' => 'status', 'status' => 'pending', 'message' => '任务已重新入队']);
        return $task;
    }
}

<?php

namespace app\process;

use app\service\AiTaskService;
use app\support\RedisClient;
use Webman\Context;
use Workerman\Worker;

/**
 * AI 任务消费进程（独立进程，阻塞式消费 Redis 队列）
 */
class AiWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        // 每任务一个 Context，保证 DB/Redis 连接池正确归还
        while (true) {
            $taskId = null;
            try {
                $redis = RedisClient::connect();
                $item = $redis->blpop([AiTaskService::QUEUE_KEY], 30);
                $redis->disconnect();
                if (is_array($item) && isset($item[1])) {
                    $taskId = (int)$item[1];
                }
            } catch (\Throwable $e) {
                echo 'AiWorker redis error: ' . $e->getMessage() . PHP_EOL;
                sleep(5);
                continue;
            }

            if (!$taskId) {
                continue;
            }

            Context::reset(new \ArrayObject());
            try {
                AiTaskService::execute($taskId);
            } catch (\Throwable $e) {
                echo 'AiWorker execute error: ' . $e->getMessage() . PHP_EOL;
                // 兜底：标记失败，避免任务永远 running
                try {
                    $task = \app\model\AiTask::find($taskId);
                    if ($task && $task->status === 'running') {
                        $task->status = 'failed';
                        $task->error_message = mb_substr($e->getMessage(), 0, 900);
                        $task->updated_at = now();
                        $task->save();
                        AiTaskService::finish($taskId, 'failed', $e->getMessage());
                    }
                } catch (\Throwable) {
                }
            } finally {
                Context::destroy();
            }
        }
    }
}

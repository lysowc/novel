<?php

namespace app\controller\admin;

use app\model\AiTask;
use app\service\AiTaskService;
use app\support\RedisClient;
use support\Request;
use Webman\Http\Response;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\ServerSentEvents;

class AiTaskController
{
    public function index(Request $request)
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 10)));
        $novelId = (int)$request->get('novel_id', 0);

        $query = AiTask::query();
        if ($novelId > 0) {
            $query->where('ref_id', $novelId)->where('ref_type', 'novel');
        }
        $total = (clone $query)->count();
        $list = $query->orderByDesc('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get()
            ->map(fn (AiTask $task) => $this->format($task));

        return ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function store(Request $request)
    {
        $type = (string)$request->post('task_type', '');
        $novelId = (int)$request->post('novel_id', 0);
        $params = $request->post('params', []);
        if (!is_array($params)) {
            $params = [];
        }
        // 参数白名单
        $allowed = ['chapter_no', 'target_words', 'instruction'];
        $params = array_intersect_key($params, array_flip($allowed));

        try {
            $task = AiTaskService::create($type, $novelId, $params);
        } catch (\Throwable $e) {
            return fail($e->getMessage());
        }
        return ok($this->format($task), '任务已创建');
    }

    public function show(Request $request, int $id)
    {
        $task = AiTask::find($id);
        if (!$task) {
            return fail('任务不存在');
        }
        return ok($this->format($task));
    }

    public function retry(Request $request, int $id)
    {
        try {
            $task = AiTaskService::retry($id);
        } catch (\Throwable $e) {
            return fail($e->getMessage());
        }
        return ok($this->format($task), '已重新入队');
    }

    /**
     * 订阅任务流（SSE）：正文增量 + 阶段状态
     * 阻塞式轮询 Redis 流缓冲，客户端断开或任务结束即退出
     */
    public function stream(Request $request, int $id)
    {
        $task = AiTask::find($id);
        if (!$task) {
            return fail('任务不存在');
        }

        $connection = $request->connection;
        $connection->send(new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ], ''));

        $send = function (array $event) use ($connection) {
            $connection->send(new ServerSentEvents([
                'data' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]));
        };

        // 任务已终态：直接发完成事件
        if (in_array($task->status, ['success', 'failed'], true)) {
            $send(['type' => 'done', 'status' => $task->status, 'error_message' => $task->error_message]);
            $connection->close();
            return new Response(200);
        }

        $startedAt = microtime(true);
        $lastEventAt = $startedAt;
        $cursor = 0;
        while (true) {
            // 客户端断开
            if ($connection->getStatus() === TcpConnection::STATUS_CLOSED) {
                break;
            }

            // 取缓冲事件：lrange + 游标（不消费），断开重连可回放已生成的全部内容
            $events = [];
            try {
                $redis = RedisClient::connect();
                $list = $redis->lrange(AiTaskService::STREAM_PREFIX . $id, 0, -1);
                $redis->disconnect();
                $events = is_array($list) ? array_reverse($list) : []; // lpush 头插，反转成时间正序
            } catch (\Throwable) {
                // 连接失败下轮重试
            }

            $done = false;
            $total = count($events);
            for ($i = $cursor; $i < $total; $i++) {
                $event = json_decode($events[$i], true);
                if (!is_array($event)) {
                    continue;
                }
                $send($event);
                $lastEventAt = microtime(true);
                if (($event['type'] ?? '') === 'done') {
                    $done = true;
                    break;
                }
            }
            $cursor = $total;
            if ($done) {
                break;
            }

            // 兜底：任务已终态但事件缺失（如 Worker 崩溃），静默 30s 后补发完成事件
            $task = AiTask::find($id);
            if ($task && in_array($task->status, ['success', 'failed'], true)
                && microtime(true) - $lastEventAt > 30) {
                $send(['type' => 'done', 'status' => $task->status, 'error_message' => $task->error_message]);
                break;
            }

            // 安全上限：30 分钟
            if (microtime(true) - $startedAt > 1800) {
                $send(['type' => 'done', 'status' => 'failed', 'error_message' => '流订阅超时']);
                break;
            }

            usleep(300000); // 300ms
        }

        $connection->close();
        return new Response(200);
    }

    private function format(AiTask $task): array
    {
        return [
            'id' => $task->id,
            'task_type' => $task->task_type,
            'task_type_text' => AiTask::typeText($task->task_type),
            'ref_id' => $task->ref_id,
            'ref_type' => $task->ref_type,
            'params' => $task->params,
            'status' => $task->status,
            'error_message' => $task->error_message,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
}

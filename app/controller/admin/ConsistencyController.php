<?php

namespace app\controller\admin;

use app\model\ConsistencyReport;
use app\model\Novel;
use app\service\AiTaskService;
use support\Request;

/**
 * 一致性审校：运行审校任务、查看历史报告
 */
class ConsistencyController
{
    /**
     * 审校报告列表（按时间倒序）
     */
    public function index(Request $request, int $id)
    {
        if (!Novel::where('id', $id)->exists()) {
            return fail('小说不存在');
        }
        $list = ConsistencyReport::where('novel_id', $id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ConsistencyReport $report) => [
                'id' => $report->id,
                'novel_id' => $report->novel_id,
                'chapter_no' => $report->chapter_no,
                'status' => $report->status,
                'report' => $report->report,
                'created_at' => $report->created_at,
            ])
            ->values();
        return ok($list);
    }

    /**
     * 运行一次一致性审校（创建异步任务）
     */
    public function run(Request $request, int $id)
    {
        if (!Novel::where('id', $id)->exists()) {
            return fail('小说不存在');
        }
        try {
            $task = AiTaskService::create('consistency_check', $id, []);
        } catch (\Throwable $e) {
            return fail($e->getMessage());
        }
        return ok([
            'id' => $task->id,
            'task_type' => $task->task_type,
            'ref_id' => $task->ref_id,
            'status' => $task->status,
            'error_message' => $task->error_message,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ], '审校任务已创建');
    }
}

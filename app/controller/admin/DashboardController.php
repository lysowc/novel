<?php

namespace app\controller\admin;

use app\model\AiLog;
use app\model\AiTask;
use app\model\Chapter;
use app\model\Idea;
use app\model\Novel;
use support\Request;

class DashboardController
{
    public function index(Request $request)
    {
        $today = date('Y-m-d 00:00:00');
        $data = [
            'novel_count' => Novel::count(),
            'chapter_count' => Chapter::count(),
            'total_words' => (int)Novel::sum('word_count'),
            'idea_count' => Idea::count(),
            'running_tasks' => AiTask::whereIn('status', ['pending', 'running'])->count(),
            'today_chapters' => Chapter::where('created_at', '>=', $today)->count(),
            'recent_tasks' => AiTask::orderByDesc('id')->limit(10)->get()->map(function (AiTask $task) {
                return [
                    'id' => $task->id,
                    'task_type' => $task->task_type,
                    'task_type_text' => AiTask::typeText($task->task_type),
                    'ref_id' => $task->ref_id,
                    'status' => $task->status,
                    'error_message' => $task->error_message,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            }),
            'recent_logs' => AiLog::orderByDesc('id')->limit(10)->get(),
        ];
        return ok($data);
    }
}

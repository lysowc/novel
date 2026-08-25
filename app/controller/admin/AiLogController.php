<?php

namespace app\controller\admin;

use app\model\AiLog;
use support\Request;

class AiLogController
{
    public function index(Request $request)
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $total = AiLog::count();
        $list = AiLog::orderByDesc('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();

        return ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function clear(Request $request)
    {
        AiLog::query()->delete();
        return ok(null, '日志已清空');
    }
}

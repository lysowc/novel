<?php

namespace app\controller\admin;

use app\model\Prompt;
use app\service\PromptService;
use support\Request;

class PromptController
{
    public function index(Request $request)
    {
        $list = Prompt::orderBy('id')->get()->map(function (Prompt $prompt) {
            return [
                'id' => $prompt->id,
                'type' => $prompt->type,
                'name' => $prompt->name,
                'description' => $prompt->description,
                'content' => $prompt->content,
                'variables' => PromptService::variables($prompt->type),
                'updated_at' => $prompt->updated_at,
            ];
        });
        return ok($list);
    }

    public function update(Request $request, int $id)
    {
        $prompt = Prompt::find($id);
        if (!$prompt) {
            return fail('Prompt 不存在');
        }
        $content = (string)$request->post('content', '');
        if (trim($content) === '') {
            return fail('Prompt 内容不能为空');
        }
        $prompt->content = $content;
        $prompt->updated_at = now();
        $prompt->save();
        return ok(null, '已保存');
    }
}

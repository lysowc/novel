<?php

namespace app\service;

use app\model\Prompt;

/**
 * Prompt 读取与渲染
 */
class PromptService
{
    /**
     * 各类型可用的占位符（用于后台 UI 提示）
     */
    public static function variables(string $type): array
    {
        $map = [
            'idea_chat' => [],
            'novel_setting' => ['{{idea_content}} 点子内容'],
            'outline' => ['{{volumes}} 卷数', '{{chapters}} 每卷章数'],
            'chapter_generate' => ['{{target_words}} 目标字数'],
            'chapter_continue' => ['{{target_words}} 目标字数'],
            'chapter_summary' => [],
            'memory_update' => [],
            'consistency_check' => [],
        ];
        return $map[$type] ?? [];
    }

    /**
     * 渲染 Prompt（替换 {{变量}}）
     */
    public static function render(string $type, array $vars = []): string
    {
        $prompt = Prompt::where('type', $type)->first();
        if (!$prompt || trim((string)$prompt->content) === '') {
            throw new \RuntimeException("Prompt 未配置: {$type}，请到 Prompt 管理里恢复默认");
        }
        $content = (string)$prompt->content;
        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string)$value, $content);
        }
        return $content;
    }
}

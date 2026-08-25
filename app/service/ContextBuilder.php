<?php

namespace app\service;

use app\model\Chapter;
use app\model\Novel;
use app\model\SystemConfig;

/**
 * AI 续写上下文组装（分层上下文）
 *
 * 正文是历史，摘要是索引，小说记忆是当前状态。
 */
class ContextBuilder
{
    /**
     * 第一层：小说设定（全量）
     */
    public static function settingText(Novel $novel): string
    {
        $setting = $novel->setting;
        if (!$setting || $setting->isEmpty()) {
            $parts = ['【小说简介】' . trim((string)$novel->description)];
            return implode("\n\n", $parts) . "\n（其余设定尚未生成）";
        }
        return $setting->toContextText();
    }

    /**
     * 第二层：小说记忆（全量）
     */
    public static function memoryText(Novel $novel): string
    {
        $memory = $novel->memory;
        return $memory && trim((string)$memory->content) !== '' ? (string)$memory->content : '（暂无小说记忆）';
    }

    /**
     * 第三层：历史章节摘要（从近到远累计，直到字符上限）
     */
    public static function summariesText(Novel $novel): string
    {
        $cap = (int)SystemConfig::get('context_summary_max_chars', 12000);
        $chapters = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->get();

        $lines = [];
        $len = 0;
        foreach ($chapters as $chapter) {
            $line = "第{$chapter->chapter_no}章 {$chapter->title}\n" . trim((string)$chapter->summary ?: '（无摘要）');
            $lineLen = mb_strlen($line);
            if ($len + $lineLen > $cap && $lines !== []) {
                break;
            }
            $lines[] = $line;
            $len += $lineLen;
        }
        if ($lines === []) {
            return '（暂无历史章节）';
        }
        return implode("\n\n", array_reverse($lines));
    }

    /**
     * 第四层：最近几章正文
     */
    public static function recentChaptersText(Novel $novel): string
    {
        $n = (int)SystemConfig::get('context_max_recent_chapters', 5);
        if ($n <= 0) {
            return '（未启用最近章节正文）';
        }
        $chapters = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->limit($n)
            ->get()
            ->reverse()
            ->values();

        if ($chapters->isEmpty()) {
            return '（暂无正文）';
        }
        $parts = [];
        foreach ($chapters as $chapter) {
            $parts[] = "第{$chapter->chapter_no}章 {$chapter->title}\n\n{$chapter->content}";
        }
        return implode("\n\n---\n\n", $parts);
    }

    /**
     * 大纲中某一章的目标信息
     */
    public static function outlineTarget(Novel $novel, int $chapterNo): string
    {
        $outline = $novel->getOutlineArray();
        foreach ($outline['volumes'] ?? [] as $volume) {
            foreach ($volume['chapters'] ?? [] as $chapter) {
                if ((int)($chapter['no'] ?? 0) === $chapterNo) {
                    return "第{$chapterNo}章 {$chapter['title']}\n本章目标：{$chapter['summary']}";
                }
            }
        }
        return "第{$chapterNo}章（大纲未覆盖此章，请根据剧情走向自由创作，并自拟合适的情节）";
    }

    /**
     * 从大纲取章标题（无则给默认）
     */
    public static function outlineTitle(Novel $novel, int $chapterNo): string
    {
        $outline = $novel->getOutlineArray();
        foreach ($outline['volumes'] ?? [] as $volume) {
            foreach ($volume['chapters'] ?? [] as $chapter) {
                if ((int)($chapter['no'] ?? 0) === $chapterNo) {
                    $title = trim((string)($chapter['title'] ?? ''));
                    if ($title !== '') {
                        return $title;
                    }
                }
            }
        }
        return "第{$chapterNo}章";
    }

    /**
     * 组装章节生成/续写的完整用户上下文
     */
    public static function buildChapterContext(Novel $novel, int $chapterNo, string $instruction = ''): string
    {
        $sections = [
            '【小说设定】' => self::settingText($novel),
            '【小说记忆（当前状态）】' => self::memoryText($novel),
            '【历史章节摘要】' => self::summariesText($novel),
            '【最近章节正文】' => self::recentChaptersText($novel),
            '【本章大纲】' => self::outlineTarget($novel, $chapterNo),
        ];
        $parts = [];
        foreach ($sections as $label => $content) {
            $parts[] = $label . "\n" . $content;
        }
        if (trim($instruction) !== '') {
            $parts[] = "【用户附加要求】\n" . trim($instruction);
        }
        return implode("\n\n", $parts);
    }
}

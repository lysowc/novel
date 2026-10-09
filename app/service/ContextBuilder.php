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
     * 第二层：小说记忆（全量，v2 结构化渲染 / 旧格式兼容）
     */
    public static function memoryText(Novel $novel): string
    {
        $memory = $novel->memory;
        if (!$memory || trim((string)$memory->content) === '') {
            return '（暂无小说记忆）';
        }
        return $memory->toContextText();
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
     * @param array $retrieved 检索召回的相关历史章节（RetrievalService::relatedChapters 的产物）
     */
    public static function buildChapterContext(Novel $novel, int $chapterNo, string $instruction = '', array $retrieved = []): string
    {
        $sections = [
            '【小说设定】' => self::settingText($novel),
            '【小说记忆（当前状态）】' => self::memoryText($novel),
            '【历史章节摘要】' => self::summariesText($novel),
        ];
        $retrievedText = RetrievalService::render($retrieved);
        if ($retrievedText !== '') {
            $sections['【相关历史章节（检索召回）】'] = $retrievedText;
        }
        $sections['【最近章节正文】'] = self::recentChaptersText($novel);
        $sections['【本章大纲】'] = self::outlineTarget($novel, $chapterNo);
        $parts = [];
        foreach ($sections as $label => $content) {
            $parts[] = $label . "\n" . $content;
        }
        if (trim($instruction) !== '') {
            $parts[] = "【用户附加要求】\n" . trim($instruction);
        }
        return implode("\n\n", $parts);
    }

    /**
     * 一致性审校用：大纲全文（卷 + 章号 + 标题 + 目标）
     */
    public static function outlineText(Novel $novel): string
    {
        $outline = $novel->getOutlineArray();
        $volumes = $outline['volumes'] ?? [];
        if ($volumes === []) {
            return '（大纲尚未生成）';
        }
        $parts = [];
        foreach ($volumes as $volume) {
            $title = (string)($volume['title'] ?? '');
            $parts[] = "【{$title}】";
            foreach (($volume['chapters'] ?? []) as $chapter) {
                $no = (int)($chapter['no'] ?? 0);
                $ct = (string)($chapter['title'] ?? '');
                $cs = trim((string)($chapter['summary'] ?? ''));
                $parts[] = $no > 0
                    ? "第{$no}章 {$ct}" . ($cs !== '' ? "：{$cs}" : '')
                    : trim($ct . ($cs !== '' ? "：{$cs}" : ''));
            }
        }
        return implode("\n", $parts);
    }

    /**
     * 一致性审校用：已写章节进度（最近 30 章全量 + 更早章节等距抽样，控制在字符上限内）
     */
    public static function writtenProgressText(Novel $novel, int $maxChars = 20000): string
    {
        $chapters = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->get(['chapter_no', 'title', 'summary']);
        if ($chapters->isEmpty()) {
            return '（还没有已写章节）';
        }

        $lineFor = function ($chapter): string {
            return "第{$chapter->chapter_no}章 {$chapter->title}：" . trim((string)$chapter->summary ?: '（无摘要）');
        };

        // 最近 30 章全量（按章号正序）
        $lines = [];
        foreach ($chapters->take(30)->reverse()->values() as $chapter) {
            $lines[] = $lineFor($chapter);
        }
        $used = array_sum(array_map('mb_strlen', $lines));

        // 更早章节在剩余预算内等距抽样
        $older = $chapters->slice(30);
        $olderCount = $older->count();
        $remaining = $maxChars - $used;
        if ($olderCount > 0 && $remaining > 0) {
            $perLineEstimate = 160;
            $budgetCount = max(1, (int)($remaining / $perLineEstimate));
            $step = max(1, (int)ceil($olderCount / $budgetCount));
            $sampled = $older->nth($step)->values();
            foreach ($sampled->reverse()->values() as $chapter) {
                $line = $lineFor($chapter);
                if (array_sum(array_map('mb_strlen', $lines)) + mb_strlen($line) > $maxChars) {
                    $lines[] = '……（更早章节已省略）';
                    break;
                }
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }
}

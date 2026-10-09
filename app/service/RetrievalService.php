<?php

namespace app\service;

use app\model\Chapter;
use app\model\Novel;
use app\model\SystemConfig;

/**
 * 相关章节检索（本地、零外部依赖）
 *
 * 设计目标：把"摘要是索引"从"按最近滚动"升级为"按相关性召回"。
 * 生成第 N 章前，用本章大纲目标 + 用户指令 + 小说记忆作为查询，
 * 在全部历史章节摘要上做 BM25（中文按双字 bigram 分词）排序，
 * 召回已经被滚动窗口丢弃、但与当前剧情相关的早期章节。
 *
 * 为什么不用向量库：个人项目章节量级（数百章）下，内存内暴力
 * BM25 完全够用，且不依赖 embedding API、无成本、结果可复现。
 */
class RetrievalService
{
    /**
     * 分词：中文按双字 bigram，英文/数字按词
     * @return array<string,int> token => 词频
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }
        $tokens = [];

        // 英文/数字词
        preg_match_all('/[a-z0-9_]{2,}/u', $text, $words);
        foreach ($words[0] as $word) {
            $tokens[$word] = ($tokens[$word] ?? 0) + 1;
        }

        // 中文双字 bigram（连续中文字符窗口）
        $cjk = preg_replace('/[^\x{4e00}-\x{9fff}]/u', "\n", $text) ?? '';
        foreach (explode("\n", $cjk) as $segment) {
            $len = mb_strlen($segment);
            if ($len < 2) {
                continue;
            }
            for ($i = 0; $i < $len - 1; $i++) {
                $bigram = mb_substr($segment, $i, 2);
                $tokens[$bigram] = ($tokens[$bigram] ?? 0) + 1;
            }
        }
        return $tokens;
    }

    /**
     * 已经处于上下文中的章节号集合：
     * 滚动摘要窗口覆盖的章节 + 最近 N 章正文。
     * 这些章节无需（也不应）被检索重复注入。
     *
     * @return array<int,int> chapter_no => 1
     */
    public static function inContextChapterNos(Novel $novel): array
    {
        $cap = (int)SystemConfig::get('context_summary_max_chars', 12000);
        $recentN = (int)SystemConfig::get('context_max_recent_chapters', 5);
        $chapters = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->get(['chapter_no', 'title', 'summary']);

        $nos = [];
        $len = 0;
        $windowed = 0;
        foreach ($chapters as $chapter) {
            $line = "第{$chapter->chapter_no}章 {$chapter->title}\n" . trim((string)$chapter->summary ?: '');
            $lineLen = mb_strlen($line);
            if ($len + $lineLen > $cap && $windowed > 0) {
                break;
            }
            $nos[$chapter->chapter_no] = 1;
            $len += $lineLen;
            $windowed++;
        }
        // 最近 N 章正文（即使摘要窗口更窄，正文本身也已在上下文中）
        foreach ($chapters->take(max(0, $recentN)) as $chapter) {
            $nos[$chapter->chapter_no] = 1;
        }
        return $nos;
    }

    /**
     * 组装检索查询：用户指令 + 本章大纲目标 + 小说记忆（当前状态）
     */
    public static function buildQuery(Novel $novel, int $chapterNo, string $instruction = ''): string
    {
        $parts = [];
        if (trim($instruction) !== '') {
            $parts[] = trim($instruction);
        }
        $parts[] = ContextBuilder::outlineTarget($novel, $chapterNo);
        $memory = $novel->memory;
        if ($memory && trim((string)$memory->content) !== '') {
            $text = $memory->toContextText();
            $parts[] = mb_substr($text, 0, 2000);
        }
        return implode("\n", $parts);
    }

    /**
     * 召回与当前章节最相关的历史章节（BM25 排序）
     *
     * @return array<int, array{chapter_no:int, title:string, summary:string, score:float}>
     */
    public static function relatedChapters(Novel $novel, int $chapterNo, string $instruction = ''): array
    {
        $enabled = (int)SystemConfig::get('retrieval_enabled', 1);
        $limit = (int)SystemConfig::get('retrieval_max_chapters', 5);
        if ($enabled <= 0 || $limit <= 0) {
            return [];
        }

        $queryText = self::buildQuery($novel, $chapterNo, $instruction);
        $queryTokens = self::tokenize($queryText);
        if ($queryTokens === []) {
            return [];
        }

        $exclude = self::inContextChapterNos($novel);
        $candidates = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->where('chapter_no', '<', $chapterNo)
            ->get(['chapter_no', 'title', 'summary']);

        // 索引：文档 = 标题 + 摘要
        $docs = [];
        foreach ($candidates as $chapter) {
            if (isset($exclude[$chapter->chapter_no])) {
                continue;
            }
            $text = trim((string)$chapter->title) . "\n" . trim((string)$chapter->summary ?: '');
            if ($text === '') {
                continue;
            }
            $docs[] = [
                'chapter_no' => $chapter->chapter_no,
                'title' => (string)$chapter->title,
                'summary' => trim((string)$chapter->summary),
                'tokens' => self::tokenize($text),
            ];
        }
        $n = count($docs);
        if ($n === 0) {
            return [];
        }

        // 词频统计（文档频率 + 平均长度）
        $df = [];
        $totalLen = 0;
        foreach ($docs as $doc) {
            $totalLen += array_sum($doc['tokens']);
            foreach (array_keys($doc['tokens']) as $token) {
                $df[$token] = ($df[$token] ?? 0) + 1;
            }
        }
        $avgdl = $totalLen / $n;

        // BM25 打分
        $k1 = 1.5;
        $b = 0.75;
        $scored = [];
        foreach ($docs as $doc) {
            $score = 0.0;
            $dl = max(1, array_sum($doc['tokens']));
            foreach ($queryTokens as $token => $qtf) {
                if (!isset($doc['tokens'][$token])) {
                    continue;
                }
                $tf = $doc['tokens'][$token];
                $idf = log(1 + ($n - $df[$token] + 0.5) / ($df[$token] + 0.5));
                $score += $idf * ($tf * ($k1 + 1)) / ($tf + $k1 * (1 - $b + $b * $dl / $avgdl)) * (1 + log($qtf + 1));
            }
            if ($score > 0) {
                $scored[] = [
                    'chapter_no' => $doc['chapter_no'],
                    'title' => $doc['title'],
                    'summary' => $doc['summary'],
                    'score' => round($score, 4),
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * 把召回结果渲染成注入上下文的文本块
     */
    public static function render(array $retrieved): string
    {
        if ($retrieved === []) {
            return '';
        }
        $lines = [];
        foreach ($retrieved as $item) {
            $lines[] = "第{$item['chapter_no']}章 {$item['title']}\n{$item['summary']}";
        }
        return implode("\n\n", $lines);
    }
}

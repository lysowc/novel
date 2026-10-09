<?php

namespace app\service;

use app\model\AiTask;
use app\model\Chapter;
use app\model\ConsistencyReport;
use app\model\Idea;
use app\model\Novel;
use app\model\NovelMemory;
use app\model\NovelSetting;
use app\model\SystemConfig;

/**
 * AI 创作业务编排：设定 / 大纲 / 章节正文 / 摘要 / 记忆
 */
class AiService
{
    /**
     * 按【】分节解析 AI 输出
     */
    public static function parseSections(string $text): array
    {
        $sections = [];
        $parts = preg_split('/【(.+?)】/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) < 2) {
            return [];
        }
        for ($i = 1; $i < count($parts); $i += 2) {
            $title = trim($parts[$i]);
            $body = trim($parts[$i + 1] ?? '');
            $sections[$title] = $body;
        }
        return $sections;
    }

    /**
     * 生成小说设定（简介/世界观/人物/势力/冲突/主线/文风）
     */
    public function generateSetting(Novel $novel, ?callable $onStage = null): void
    {
        $onStage && $onStage('request', 'AI 正在生成小说设定...');
        $idea = Idea::where('novel_id', $novel->id)->first();
        $ideaContent = $idea && trim((string)$idea->content) !== ''
            ? (string)$idea->content
            : trim((string)$novel->description);

        $system = PromptService::render('novel_setting', ['idea_content' => $ideaContent]);
        $result = (new AiClient())->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => '请根据以上要求，输出完整的小说设定。'],
        ], ['task_type' => 'generate_setting', 'max_tokens' => 8192]);

        $sections = self::parseSections($result['text']);
        $fieldMap = [
            '小说简介' => 'description',
            '世界观' => 'world_view',
            '主要人物设定' => 'characters',
            '主要势力' => 'factions',
            '核心冲突' => 'conflicts',
            '故事主线' => 'main_plot',
            '文风要求' => 'style',
        ];

        $setting = NovelSetting::where('novel_id', $novel->id)->first();
        if (!$setting) {
            $setting = new NovelSetting();
            $setting->novel_id = $novel->id;
        }
        foreach ($fieldMap as $title => $field) {
            $value = trim($sections[$title] ?? '');
            if ($field === 'description') {
                if ($value !== '') {
                    $novel->description = $value;
                }
            } elseif ($value !== '') {
                $setting->{$field} = $value;
            }
        }
        $setting->save();
        $novel->save();
        $onStage && $onStage('done', '设定生成完成');
    }

    /**
     * 生成章节大纲（卷/章 JSON）
     */
    public function generateOutline(Novel $novel, ?callable $onStage = null): void
    {
        $onStage && $onStage('request', 'AI 正在生成章节大纲...');
        $volumes = (int)SystemConfig::get('outline_volumes', 3);
        $chapters = (int)SystemConfig::get('outline_chapters_per_volume', 20);

        $system = PromptService::render('outline', ['volumes' => $volumes, 'chapters' => $chapters]);
        $result = (new AiClient())->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "【小说设定】\n" . ContextBuilder::settingText($novel)],
        ], [
            'task_type' => 'generate_outline',
            'max_tokens' => 16384,
            'timeout' => (int)SystemConfig::get('ai_http_timeout', 120) * 3,
        ]);

        $data = self::extractJson($result['text']);
        if (!isset($data['volumes']) || !is_array($data['volumes'])) {
            throw new \RuntimeException('大纲解析失败：AI 未返回合法 JSON');
        }
        // 重新编号，保证章号连续
        $no = 1;
        $volumes = [];
        foreach ($data['volumes'] as $volume) {
            $vol = ['title' => (string)($volume['title'] ?? ''), 'chapters' => []];
            foreach ($volume['chapters'] ?? [] as $chapter) {
                $vol['chapters'][] = [
                    'no' => $no,
                    'title' => (string)($chapter['title'] ?? "第{$no}章"),
                    'summary' => (string)($chapter['summary'] ?? ''),
                ];
                $no++;
            }
            $volumes[] = $vol;
        }
        $novel->setOutlineArray(['volumes' => $volumes]);
        $onStage && $onStage('done', '大纲生成完成，共 ' . ($no - 1) . ' 章');
    }

    /**
     * 生成/续写/重写一章正文，成功后生成摘要并更新记忆
     * 全程「先成功后保存」：任何环节失败不影响已有章节
     */
    public function generateChapter(Novel $novel, AiTask $task, ?callable $onDelta = null, ?callable $onStage = null): Chapter
    {
        $params = $task->params ?? [];
        $type = $task->task_type;

        if ($type === 'generate_chapter' || $type === 'regenerate_chapter') {
            $chapterNo = (int)($params['chapter_no'] ?? 0);
            if ($chapterNo <= 0) {
                throw new \RuntimeException('缺少 chapter_no 参数');
            }
            // 重写时确认章节存在（生成成功后才覆盖）
            $exists = Chapter::where('novel_id', $novel->id)->where('chapter_no', $chapterNo)->exists();
            if ($type === 'regenerate_chapter' && !$exists) {
                throw new \RuntimeException("第{$chapterNo}章不存在");
            }
        } else {
            $chapterNo = (int)Chapter::where('novel_id', $novel->id)->max('chapter_no') + 1;
        }

        $targetWords = (int)($params['target_words'] ?? SystemConfig::get('chapter_target_words', 3000));
        $instruction = trim((string)($params['instruction'] ?? ''));
        // 输出上限：目标字数 × 2（中文约 1 token ≈ 0.5~1 字），防止被 Provider 默认上限静默截断
        $maxTokens = max(1024, min(16384, $targetWords * 2));

        // 1. 检索相关历史章节（可召回记忆：按相关性而非按最近）
        $retrieved = [];
        if ((int)SystemConfig::get('retrieval_enabled', 1) > 0) {
            $onStage && $onStage('retrieval', '正在检索相关历史章节...');
            $retrieved = RetrievalService::relatedChapters($novel, $chapterNo, $instruction);
            $onStage && $onStage('retrieval_done', $retrieved !== []
                ? '已从历史中召回 ' . count($retrieved) . ' 个相关章节'
                : '未找到强相关历史章节，使用常规上下文');
        }

        // 2. 生成正文（流式）
        $onStage && $onStage('content', "开始生成第{$chapterNo}章（目标 {$targetWords} 字）...");
        $promptType = $type === 'continue_chapter' ? 'chapter_continue' : 'chapter_generate';
        $system = PromptService::render($promptType, ['target_words' => $targetWords]);
        $userContext = ContextBuilder::buildChapterContext($novel, $chapterNo, $instruction, $retrieved);

        $result = (new AiClient())->chatStream(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContext],
            ],
            ['task_type' => $type, 'max_tokens' => $maxTokens],
            $onDelta
        );
        $content = trim($result['text']);
        if ($content === '') {
            throw new \RuntimeException('AI 返回内容为空');
        }

        $onStage && $onStage('content_done', '正文生成完成（' . word_count($content) . ' 字），正在保存...');

        // 3. 保存章节（此时才落库，覆盖式保存）
        $chapter = Chapter::where('novel_id', $novel->id)->where('chapter_no', $chapterNo)->first();
        if (!$chapter) {
            $chapter = new Chapter();
            $chapter->novel_id = $novel->id;
            $chapter->chapter_no = $chapterNo;
        }
        $chapter->title = ContextBuilder::outlineTitle($novel, $chapterNo);
        $chapter->content = $content;
        $chapter->word_count = word_count($content);
        $chapter->status = 'published';
        $chapter->save();
        $novel->recount();

        // 4. 生成摘要
        $onStage && $onStage('summary', '正文已保存，正在生成章节摘要...');
        try {
            $summary = $this->generateSummary($novel, $chapter);
            $chapter->summary = $summary;
            $chapter->save();
            $onStage && $onStage('summary_done', '摘要生成完成');
        } catch (\Throwable $e) {
            $onStage && $onStage('summary_failed', '摘要生成失败：' . $e->getMessage());
            throw new \RuntimeException('章节已保存，但摘要生成失败：' . $e->getMessage());
        }

        // 5. 更新小说记忆
        $onStage && $onStage('memory', '正在更新小说记忆...');
        try {
            $this->updateMemory($novel);
            $onStage && $onStage('memory_done', '小说记忆已更新');
        } catch (\Throwable $e) {
            $onStage && $onStage('memory_failed', '记忆更新失败：' . $e->getMessage());
            throw new \RuntimeException('章节已保存，但记忆更新失败：' . $e->getMessage());
        }

        // 6. 自动一致性审校（每 N 章触发，配置项 consistency_auto_interval，0=关闭）
        $interval = (int)SystemConfig::get('consistency_auto_interval', 0);
        if ($interval > 0 && $chapterNo % $interval === 0) {
            try {
                AiTaskService::enqueue('consistency_check', $novel->id, ['chapter_no' => $chapterNo]);
                $onStage && $onStage('consistency_queued', "已自动安排第{$chapterNo}章后的一致性审校");
            } catch (\Throwable $e) {
                // 自动审校入队失败不影响正文任务本身
                $onStage && $onStage('consistency_queued', '自动审校入队失败：' . $e->getMessage());
            }
        }

        return $chapter;
    }

    /**
     * 为指定章节生成摘要（用于任务补跑）
     */
    public function generateSummaryForChapter(Novel $novel, AiTask $task, ?callable $onStage = null): void
    {
        $chapterNo = (int)($task->params['chapter_no'] ?? 0);
        $chapter = Chapter::where('novel_id', $novel->id)->where('chapter_no', $chapterNo)->first();
        if (!$chapter) {
            throw new \RuntimeException("第{$chapterNo}章不存在");
        }
        $onStage && $onStage('request', "正在为第{$chapterNo}章生成摘要...");
        $chapter->summary = $this->generateSummary($novel, $chapter);
        $chapter->save();
        $onStage && $onStage('done', '摘要生成完成');
    }

    /**
     * 生成单章摘要
     */
    public function generateSummary(Novel $novel, Chapter $chapter): string
    {
        $system = PromptService::render('chapter_summary', []);
        $context = ContextBuilder::settingText($novel)
            . "\n\n【小说记忆（当前状态）】\n" . ContextBuilder::memoryText($novel)
            . "\n\n【本章标题】{$chapter->title}"
            . "\n\n【本章正文】\n" . $chapter->content;

        $result = (new AiClient())->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $context],
        ], ['task_type' => 'generate_summary']);

        $summary = trim($result['text']);
        if ($summary === '') {
            throw new \RuntimeException('摘要生成结果为空');
        }
        return $summary;
    }

    /**
     * 更新小说记忆：旧记忆 + 最新章 → AI 整理 → 结构化 v2 整体替换
     * 升级路径：旧格式（自由对象 / key-value 数组）会在本次更新中被迁移为 v2 记忆槽
     */
    public function updateMemory(Novel $novel): void
    {
        $last = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->first();
        if (!$last) {
            throw new \RuntimeException('还没有已发布的章节，无法更新记忆');
        }

        $oldMemory = NovelMemory::of($novel);
        $oldText = trim((string)$oldMemory->content) !== ''
            ? $oldMemory->toContextText()
            : '（暂无旧记忆，这是第一次建立记忆）';

        $chapterContent = (string)$last->content;
        if (mb_strlen($chapterContent) > 6000) {
            $chapterContent = mb_substr($chapterContent, 0, 6000) . "\n……（正文截断）";
        }

        $system = PromptService::render('memory_update', []);
        $user = "【旧记忆】\n{$oldText}\n\n"
            . "【最新一章】\n第{$last->chapter_no}章 {$last->title}\n"
            . "本章摘要：{$last->summary}\n\n"
            . "本章正文：\n{$chapterContent}";

        $result = (new AiClient())->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], ['task_type' => 'update_memory']);

        $data = self::extractJson($result['text']);
        if ($data === []) {
            throw new \RuntimeException('记忆更新失败：AI 未返回合法 JSON');
        }
        $data = self::normalizeMemory($data);

        $memory = NovelMemory::of($novel);
        $memory->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $memory->updated_at = now();
        $memory->save();
    }

    /**
     * 一致性审校：大纲 vs 已写章节进度 vs 小说记忆，产出结构化报告并落库
     */
    public function checkConsistency(Novel $novel, AiTask $task, ?callable $onStage = null): void
    {
        $onStage && $onStage('request', 'AI 正在审校大纲与正文的一致性...');

        $last = Chapter::where('novel_id', $novel->id)
            ->where('status', 'published')
            ->orderByDesc('chapter_no')
            ->first();
        if (!$last) {
            throw new \RuntimeException('还没有已发布的章节，无法审校');
        }

        $system = PromptService::render('consistency_check', []);
        $user = "【小说大纲】\n" . ContextBuilder::outlineText($novel)
            . "\n\n【已写章节进度】\n" . ContextBuilder::writtenProgressText($novel)
            . "\n\n【小说记忆（当前状态）】\n" . ContextBuilder::memoryText($novel);

        $result = (new AiClient())->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], ['task_type' => 'consistency_check', 'max_tokens' => 4096]);

        $data = self::extractJson($result['text']);
        $report = self::normalizeReport($data);
        if ($report === null) {
            throw new \RuntimeException('审校失败：AI 未返回合法报告');
        }

        $row = new ConsistencyReport();
        $row->novel_id = $novel->id;
        $row->chapter_no = (int)$last->chapter_no;
        $row->status = $report['status'];
        $row->report = $report;
        $row->created_at = now();
        $row->save();

        $count = count($report['issues']);
        $onStage && $onStage('done', $count > 0
            ? "审校完成（{$report['status']}）：发现 {$count} 个问题"
            : '审校完成：未发现明显问题');
    }

    /**
     * 把 AI 返回的记忆数据规整为 v2 结构（缺槽补默认值、条目形状归一、旧字段迁移）
     */
    public static function normalizeMemory(array $data): array
    {
        $out = ['schema' => NovelMemory::SCHEMA_V2];

        // current_state（兼容旧字段 current_location / current_time / main_plot）
        $cs = is_array($data['current_state'] ?? null) ? $data['current_state'] : [];
        $out['current_state'] = [
            'location' => trim((string)($cs['location'] ?? $data['current_location'] ?? '')),
            'time' => trim((string)($cs['time'] ?? $data['current_time'] ?? '')),
            'plot_progress' => trim((string)($cs['plot_progress'] ?? $data['main_plot'] ?? '')),
        ];

        // characters（兼容旧字段 main_character 单对象）
        $rawChars = $data['characters'] ?? null;
        if (!is_array($rawChars) && is_array($data['main_character'] ?? null)) {
            $rawChars = [$data['main_character']];
        }
        $chars = [];
        foreach ((array)$rawChars as $c) {
            if (is_string($c)) {
                $chars[] = ['name' => trim($c), 'status' => '', 'relationships' => '', 'goals' => ''];
            } elseif (is_array($c)) {
                $chars[] = [
                    'name' => trim((string)($c['name'] ?? '')),
                    'status' => trim((string)($c['status'] ?? '')),
                    'relationships' => trim((string)($c['relationships'] ?? '')),
                    'goals' => trim((string)($c['goals'] ?? '')),
                ];
            }
        }
        $out['characters'] = array_values(array_filter($chars, fn ($c) => $c['name'] !== ''));

        // foreshadowing（字符串或对象）
        $fores = [];
        foreach ((array)($data['foreshadowing'] ?? []) as $f) {
            if (is_string($f)) {
                $fores[] = ['description' => trim($f), 'planted_chapter' => 0, 'status' => 'open', 'resolved_chapter' => 0];
            } elseif (is_array($f)) {
                $status = (string)($f['status'] ?? 'open');
                $fores[] = [
                    'description' => trim((string)($f['description'] ?? $f['name'] ?? '')),
                    'planted_chapter' => (int)($f['planted_chapter'] ?? 0),
                    'status' => in_array($status, ['open', 'resolved'], true) ? $status : 'open',
                    'resolved_chapter' => (int)($f['resolved_chapter'] ?? 0),
                ];
            }
        }
        $out['foreshadowing'] = array_values(array_filter($fores, fn ($f) => $f['description'] !== ''));

        // 字符串列表槽
        $stringList = function (string $slot) use ($data): array {
            $items = [];
            foreach ((array)($data[$slot] ?? []) as $item) {
                if (is_array($item)) {
                    $item = (string)($item['description'] ?? $item['name'] ?? $item['event'] ?? '');
                }
                $item = trim((string)$item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
            return $items;
        };
        $out['world_facts'] = $stringList('world_facts');
        $out['unresolved_events'] = $stringList('unresolved_events');

        // timeline
        $timeline = [];
        foreach ((array)($data['timeline'] ?? []) as $event) {
            if (is_string($event)) {
                $timeline[] = ['chapter' => 0, 'event' => trim($event)];
            } elseif (is_array($event)) {
                $timeline[] = ['chapter' => (int)($event['chapter'] ?? 0), 'event' => trim((string)($event['event'] ?? ''))];
            }
        }
        $out['timeline'] = array_values(array_filter($timeline, fn ($e) => $e['event'] !== ''));

        // important_items
        $items = [];
        foreach ((array)($data['important_items'] ?? []) as $item) {
            if (is_string($item)) {
                $items[] = ['name' => trim($item), 'status' => ''];
            } elseif (is_array($item)) {
                $items[] = ['name' => trim((string)($item['name'] ?? '')), 'status' => trim((string)($item['status'] ?? ''))];
            }
        }
        $out['important_items'] = array_values(array_filter($items, fn ($i) => $i['name'] !== ''));

        // style_notes（可选，纯文本）
        $style = $data['style_notes'] ?? '';
        $out['style_notes'] = is_array($style) ? implode("\n", array_map('strval', $style)) : trim((string)$style);

        return $out;
    }

    /**
     * 规整一致性审校报告；输入为空（AI 未返回 JSON）时返回 null
     */
    public static function normalizeReport(array $data): ?array
    {
        if ($data === []) {
            return null;
        }
        $status = (string)($data['status'] ?? '');
        if (!in_array($status, ['ok', 'warning', 'critical'], true)) {
            $status = 'warning';
        }
        $issues = [];
        foreach ((array)($data['issues'] ?? []) as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $severity = (string)($issue['severity'] ?? 'minor');
            if (!in_array($severity, ['minor', 'major', 'critical'], true)) {
                $severity = 'minor';
            }
            $desc = trim((string)($issue['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $related = array_values(array_filter(
                array_map('intval', (array)($issue['related_chapters'] ?? [])),
                fn ($n) => $n > 0
            ));
            $issues[] = [
                'severity' => $severity,
                'type' => trim((string)($issue['type'] ?? 'other')),
                'description' => $desc,
                'suggestion' => trim((string)($issue['suggestion'] ?? '')),
                'related_chapters' => $related,
            ];
        }
        if ($issues === [] && $status !== 'ok') {
            $status = 'ok';
        }
        return [
            'status' => $status,
            'summary' => trim((string)($data['summary'] ?? '')),
            'issues' => $issues,
        ];
    }

    /**
     * 从 AI 输出中提取 JSON（容忍 markdown 代码块）
     */
    public static function extractJson(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : [];
    }
}

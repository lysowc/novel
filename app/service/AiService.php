<?php

namespace app\service;

use app\model\AiTask;
use app\model\Chapter;
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

        // 1. 生成正文（流式）
        $onStage && $onStage('content', "开始生成第{$chapterNo}章（目标 {$targetWords} 字）...");
        $promptType = $type === 'continue_chapter' ? 'chapter_continue' : 'chapter_generate';
        $system = PromptService::render($promptType, ['target_words' => $targetWords]);
        $userContext = ContextBuilder::buildChapterContext($novel, $chapterNo, $instruction);

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

        // 2. 保存章节（此时才落库，覆盖式保存）
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

        // 3. 生成摘要
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

        // 4. 更新小说记忆
        $onStage && $onStage('memory', '正在更新小说记忆...');
        try {
            $this->updateMemory($novel);
            $onStage && $onStage('memory_done', '小说记忆已更新');
        } catch (\Throwable $e) {
            $onStage && $onStage('memory_failed', '记忆更新失败：' . $e->getMessage());
            throw new \RuntimeException('章节已保存，但记忆更新失败：' . $e->getMessage());
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
     * 更新小说记忆：旧记忆 + 最新章 → AI 整理 → 整体替换
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

        $oldMemory = NovelMemory::of($novel)->content;
        $oldMemory = $oldMemory && trim((string)$oldMemory) !== '' ? (string)$oldMemory : '{}';

        $chapterContent = (string)$last->content;
        if (mb_strlen($chapterContent) > 6000) {
            $chapterContent = mb_substr($chapterContent, 0, 6000) . "\n……（正文截断）";
        }

        $system = PromptService::render('memory_update', []);
        $user = "【旧记忆】\n{$oldMemory}\n\n"
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

        $memory = NovelMemory::of($novel);
        $memory->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $memory->updated_at = now();
        $memory->save();
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

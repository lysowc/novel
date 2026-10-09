<?php

namespace app\command;

use app\model\AiTask;
use app\model\Chapter;
use app\model\Novel;
use app\model\NovelMemory;
use app\model\SystemConfig;
use app\service\AiService;
use app\service\AiTaskService;
use app\service\ContextBuilder;
use app\service\RetrievalService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 回归自测命令：验证检索式上下文 / 结构化记忆 / 一致性审校三大升级
 * 用法：php webman verify:novel-memory（自动造数并清理，需 MySQL 可用）
 */
#[AsCommand(name: 'verify:novel-memory', description: '自测三大升级（检索/记忆v2/审校）')]
class VerifyNovelMemory extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pass = 0;
        $fail = 0;
        $ok = function (string $msg, bool $cond) use ($output, &$pass, &$fail) {
            if ($cond) {
                $pass++;
                $output->writeln("<info>  ✔ {$msg}</info>");
            } else {
                $fail++;
                $output->writeln("<error>  ✘ {$msg}</error>");
            }
        };

        // 1. 分词
        $tokens = RetrievalService::tokenize('古戒残魂 lowvoice 苏醒');
        $ok('bigram 分词：中文双字', isset($tokens['古戒']) && isset($tokens['残魂']));
        $ok('bigram 分词：英文词', isset($tokens['lowvoice']));

        // 2. 造数据：10 章，第 3 章是"古戒"关键章节，越靠后越无关
        $novel = new Novel();
        $novel->title = '自测小说_' . time();
        $novel->description = '自测用';
        $novel->status = 'draft';
        $novel->save();
        $novelId = $novel->id;

        $keyChapter = '林凡在试炼中得到神秘古戒，戒中出现残魂低语，暗示古戒与天元秘境有关。';
        $generic = fn (int $n) => "林凡修炼突破，第{$n}次小比获胜，结识新的同门，宗门日常平静推进。";
        for ($n = 1; $n <= 10; $n++) {
            $c = new Chapter();
            $c->novel_id = $novelId;
            $c->chapter_no = $n;
            $c->title = "第{$n}章 测试章节";
            $c->summary = $n === 3 ? $keyChapter : $generic($n);
            $c->content = '正文内容占位。' . str_repeat('字', 500);
            $c->word_count = 500;
            $c->status = 'published';
            $c->save();
        }
        $novel->recount();
        $ok('造数：10 章已建', Chapter::where('novel_id', $novelId)->count() === 10);

        // 3. 临时配置：缩小滚动窗口，让第 3 章必然被"最近"策略丢弃
        $origCaps = [
            'context_summary_max_chars' => SystemConfig::get('context_summary_max_chars', '12000'),
            'context_max_recent_chapters' => SystemConfig::get('context_max_recent_chapters', '5'),
            'retrieval_enabled' => SystemConfig::get('retrieval_enabled', '1'),
            'retrieval_max_chapters' => SystemConfig::get('retrieval_max_chapters', '5'),
        ];
        SystemConfig::set([
            'context_summary_max_chars' => '150',
            'context_max_recent_chapters' => '5',
            'retrieval_enabled' => '1',
            'retrieval_max_chapters' => '3',
        ]);

        $novel->outline = json_encode(['volumes' => [[
            'title' => '第一卷',
            'chapters' => [[
                'no' => 11,
                'title' => '第11章 古戒苏醒',
                'summary' => '古戒中的残魂彻底苏醒，与林凡对话并揭示天元秘境的秘密。',
            ]],
        ]]], JSON_UNESCAPED_UNICODE);
        $novel->save();

        $window = ContextBuilder::summariesText($novel);
        $ok('滚动窗口不含第 3 章（设计前提成立）', !str_contains($window, '第3章'));

        $retrieved = RetrievalService::relatedChapters($novel, 11, '');
        $topNo = $retrieved[0]['chapter_no'] ?? 0;
        $ok('检索召回第 3 章（古戒章节）', $topNo === 3);
        $ok('检索结果不含已在上下文的近章', collect($retrieved)->every(fn ($r) => $r['chapter_no'] <= 5));

        $ctx = ContextBuilder::buildChapterContext($novel, 11, '', $retrieved);
        $ok('生成上下文含【相关历史章节（检索召回）】', str_contains($ctx, '【相关历史章节（检索召回）】'));
        $ok('上下文含召回内容', str_contains($ctx, '第3章'));

        // 4. 旧格式记忆渲染 + 规整
        $legacy = new NovelMemory();
        $legacy->novel_id = $novelId;
        $legacy->content = json_encode(['current_location' => '青云宗', 'main_character' => ['name' => '林凡'], 'foreshadowing' => ['残魂苏醒']], JSON_UNESCAPED_UNICODE);
        $legacy->save();
        $text = $legacy->toContextText();
        $ok('旧格式记忆可渲染', str_contains($text, 'current_location') && str_contains($text, '残魂苏醒'));

        $normalized = AiService::normalizeMemory([
            'current_location' => '青云宗',
            'main_character' => ['name' => '林凡', 'realm' => '炼气九层'],
            'foreshadowing' => [
                ['description' => '残魂苏醒', 'status' => 'open'],
                '血魔宗觊觎古戒',
            ],
            'important_items' => ['上古青铜戒'],
        ]);
        $ok('normalizeMemory 产出 v2 schema', ($normalized['schema'] ?? '') === NovelMemory::SCHEMA_V2);
        $ok('normalizeMemory 迁移旧人物字段', ($normalized['characters'][0]['name'] ?? '') === '林凡');
        $ok('normalizeMemory 规整伏笔条目', count($normalized['foreshadowing']) === 2 && $normalized['foreshadowing'][0]['status'] === 'open');
        $ok('normalizeMemory 规整物品条目', $normalized['important_items'][0]['name'] === '上古青铜戒');

        $v2 = NovelMemory::of($novel);
        $v2->content = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $v2->updated_at = now();
        $v2->save();
        $v2text = $v2->toContextText();
        $ok('v2 记忆结构化渲染', str_contains($v2text, '【当前状态】') && str_contains($v2text, '【未回收伏笔】') && str_contains($v2text, '残魂苏醒'));

        // 5. 审校报告规整
        $report = AiService::normalizeReport([
            'status' => 'warning',
            'summary' => '总体正常',
            'issues' => [
                ['severity' => 'major', 'type' => 'foreshadowing_dropped', 'description' => '伏笔遗忘', 'suggestion' => '重新激活', 'related_chapters' => [3, '5']],
                ['severity' => 'critical', 'description' => '无 type 兜底'],
            ],
        ]);
        $ok('normalizeReport 保留 2 个问题', count($report['issues']) === 2);
        $ok('normalizeReport 章号过滤为 int', $report['issues'][0]['related_chapters'] === [3, 5]);
        $ok('normalizeReport 空输入返回 null', AiService::normalizeReport([]) === null);

        // 6. 内部入队（无进行中任务检查）
        $task = AiTaskService::enqueue('consistency_check', $novelId, []);
        $ok('enqueue 创建审校任务', $task->id > 0 && AiTask::find($task->id)->status === 'pending');

        // 7. 进度文本（抽样）
        $progress = ContextBuilder::writtenProgressText($novel, 600);
        $ok('审校进度文本抽样', str_contains($progress, '第10章') && mb_strlen($progress) <= 700);

        // 清理
        AiTask::where('ref_id', $novelId)->delete();
        Chapter::where('novel_id', $novelId)->delete();
        NovelMemory::where('novel_id', $novelId)->delete();
        $novel->delete();
        SystemConfig::set($origCaps);
        $output->writeln('');
        $output->writeln("<comment>自测结束：通过 {$pass} 项 / 失败 {$fail} 项（测试数据已清理）</comment>");
        return $fail === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}

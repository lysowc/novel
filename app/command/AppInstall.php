<?php

namespace app\command;

use app\model\Admin;
use app\model\Category;
use app\model\Prompt;
use app\model\SystemConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:install', description: '初始化系统：执行迁移 + 写入默认数据（管理员/分类/Prompt/系统配置）')]
class AppInstall extends Command
{
    protected function configure(): void
    {
        $this->addOption('username', 'u', InputOption::VALUE_OPTIONAL, '管理员账号', 'admin');
        $this->addOption('password', 'p', InputOption::VALUE_OPTIONAL, '管理员密码', 'admin123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. 迁移
        $output->writeln('<info>[1/4] 执行数据库迁移...</info>');
        $migrate = $this->getApplication()->find('migrate');
        $code = $migrate->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'migrate']), $output);
        if ($code !== Command::SUCCESS) {
            return $code;
        }

        // 2. 管理员
        $output->writeln('<info>[2/4] 写入管理员...</info>');
        $username = (string)$input->getOption('username');
        $password = (string)$input->getOption('password');
        $admin = Admin::where('username', $username)->first();
        if (!$admin) {
            $admin = new Admin();
            $admin->username = $username;
        }
        $admin->password = password_hash($password, PASSWORD_DEFAULT);
        $admin->save();
        $output->writeln("<comment>  管理员账号: {$username}  密码: {$password}</comment>");

        // 3. 分类
        $output->writeln('<info>[3/4] 写入默认分类...</info>');
        if (Category::count() === 0) {
            foreach (['玄幻', '仙侠', '都市', '科幻', '历史', '悬疑', '游戏'] as $i => $name) {
                $category = new Category();
                $category->name = $name;
                $category->description = '';
                $category->sort = $i + 1;
                $category->status = 1;
                $category->save();
            }
        }

        // 4. Prompt 种子
        $output->writeln('<info>[4/4] 写入默认 Prompt...</info>');
        $this->seedPrompts($output);

        // 5. 系统配置
        $this->seedConfig($output);

        $output->writeln('<info>初始化完成 ✔  启动服务: php start.php start</info>');
        return Command::SUCCESS;
    }

    private function seedPrompts(OutputInterface $output): void
    {
        $defaults = [
            [
                'type' => 'idea_chat',
                'name' => '点子聊天',
                'description' => '与作者讨论打磨小说点子的对话助手',
                'content' => <<<'PROMPT'
你是一位资深的网文策划编辑，擅长把作者模糊的灵感打磨成可落地的小说点子。

你的职责是与作者讨论，逐步明确：
1. 题材与核心创意（穿越/重生/系统/科幻/仙侠……）
2. 主角设定（身份、性格、金手指或特殊能力）
3. 世界观与冲突（世界规则、主要矛盾、反派）
4. 卖点与爽点（读者为什么想看）

交流要求：
- 每次回复简洁（不超过 300 字），聚焦当前讨论点
- 主动追问关键缺口，一次最多问 2~3 个问题
- 多给出具体、有网文感的建议与选项，帮助作者决策
- 不要一上来就写长篇完整设定，要循序渐进
PROMPT,
            ],
            [
                'type' => 'novel_setting',
                'name' => '小说设定生成',
                'description' => '根据点子生成简介/世界观/人物/势力/冲突/主线/文风',
                'content' => <<<'PROMPT'
你是一位资深网文作者。请根据下面的小说点子，为这部小说生成完整的基础设定。

输出必须包含以下 7 个部分（使用【】作为标题，不要遗漏任何一部分）：

【小说简介】
100~200 字，面向读者的简介，交代主角、处境、核心悬念，有吸引力。

【世界观】
世界的运行规则、时代背景、力量体系（如修炼境界/异能等级）、重要地理与势力格局。要具体，不能空泛。

【主要人物设定】
主角（姓名、身份、性格、目标、金手指）+ 2~4 名重要角色（姓名、身份、性格、与主角关系）。

【主要势力】
各方势力的名称、立场、实力与目标。

【核心冲突】
贯穿全书的主要矛盾，主角要对抗什么、追求什么。

【故事主线】
分阶段描述主线走向（至少 4 个阶段，每个阶段 1~2 句话）。

【文风要求】
适合本作的文风建议（节奏、视角、语言风格）。

点子如下：
{{idea_content}}
PROMPT,
            ],
            [
                'type' => 'outline',
                'name' => '章节大纲生成',
                'description' => '根据设定生成分卷章节大纲（JSON）',
                'content' => <<<'PROMPT'
你是一位资深网文大纲策划。请根据下面的小说设定，为长篇小说生成分卷章节大纲。

要求：
- 共 {{volumes}} 卷，每卷 {{chapters}} 章
- 每章给出：标题 + 一句话简介（不超过 50 字，写清楚这一章发生了什么）
- 剧情层层递进：铺垫、冲突、升级、高潮、转折要合理分布
- 卷与卷之间要有关键转折点
- 严格只输出 JSON，格式如下（不要输出任何其他文字或代码块标记）：
{"volumes":[{"title":"第一卷 xxx","chapters":[{"no":1,"title":"章标题","summary":"一句话简介"}]}]}
PROMPT,
            ],
            [
                'type' => 'chapter_generate',
                'name' => '章节正文生成',
                'description' => '按大纲生成指定章节正文',
                'content' => <<<'PROMPT'
你是一位资深网文作者，正在创作长篇小说。请根据下面提供的：小说设定、小说记忆（当前状态）、历史章节摘要、相关历史章节（检索召回）、最近章节正文、本章大纲，撰写本章正文。

写作要求：
- 本章约 {{target_words}} 字
- 文风为网文风格：节奏明快、对话与描写均衡、爽点安排合理
- 严格延续已有设定、人物性格与剧情逻辑，绝不与历史章节矛盾
- 若提供了【相关历史章节（检索召回）】，这些是与本章剧情相关的早期章节，优先参考它们回收伏笔或衔接支线
- 开头自然衔接最近一章的结尾
- 结尾要留钩子：推进主线、埋新伏笔或制造悬念
- 只输出正文内容本身：不要输出章节标题、不要写"第X章"、不要写"本章完"、不要任何解释或注释
PROMPT,
            ],
            [
                'type' => 'chapter_summary',
                'name' => '章节摘要生成',
                'description' => '为章节生成高信息密度摘要（给 AI 回忆剧情用）',
                'content' => <<<'PROMPT'
你是一位小说编辑。请为下面这一章生成"章节摘要"。

摘要的用途是给 AI 后续续写时快速回忆历史剧情，因此必须信息密度高（100~500 字），必须包含：
- 谁做了什么、发生了什么
- 人物状态变化（境界、身份、伤势、关系等）
- 新增人物、新增设定、重要物品
- 剧情结果、新伏笔、未解决的问题

直接输出摘要正文，不要任何标题或解释。
PROMPT,
            ],
            [
                'type' => 'memory_update',
                'name' => '小说记忆更新',
                'description' => '旧记忆 + 最新章 → 结构化记忆槽（v2）',
                'content' => <<<'PROMPT'
你负责维护一部长篇小说的"结构化小说记忆"。小说记忆是 AI 续写时了解"故事当前状态"的权威数据，采用固定记忆槽结构，保证长篇连载几百章后依然能准确追踪伏笔、人物与世界观。

请根据【旧记忆】和【最新一章】把记忆更新为最新状态：
- 合并而非重写：旧记忆里仍然有效的信息必须保留
- current_state：当前地点、时间线、主线剧情进展（1~3 句）
- characters：每个重要人物一条（姓名、当前状态、重要关系、当前目标），只保留近期活跃人物，出场很少的次要人物可删除
- foreshadowing：每一条伏笔含 description、planted_chapter（约第几章埋下，未知填 0）、status（open 未回收 / resolved 已回收）、resolved_chapter（本章回收了某伏笔时，改为 resolved 并记录本章号）
- world_facts：本章及旧记忆中新出现、会影响后续剧情的世界观事实（新规则、新地点、新势力等），仍然有效的事实要保留
- timeline：关键事件时间线，只保留最近 20 条，更早事件的信息合并进 current_state.plot_progress
- unresolved_events：当前未解决的问题或任务
- important_items：重要物品及其状态
- style_notes：文风与写作注意事项（可选）
- 所有内容精简准确，避免冗余描述，每条信息都要为"未来续写"服务

如果旧记忆是旧版格式（自由 JSON 或键值数组），请把其中有价值的信息迁移到上面的记忆槽中。

严格只输出 JSON（不要输出任何其他文字），结构如下：
{
  "schema": "v2",
  "current_state": {"location": "", "time": "", "plot_progress": ""},
  "characters": [{"name": "", "status": "", "relationships": "", "goals": ""}],
  "foreshadowing": [{"description": "", "planted_chapter": 0, "status": "open", "resolved_chapter": 0}],
  "world_facts": [""],
  "timeline": [{"chapter": 0, "event": ""}],
  "unresolved_events": [""],
  "important_items": [{"name": "", "status": ""}],
  "style_notes": ""
}
PROMPT,
            ],
            [
                'type' => 'consistency_check',
                'name' => '一致性审校',
                'description' => '对照大纲与已写章节检测漂移、矛盾、伏笔遗忘，产出结构化报告',
                'content' => <<<'PROMPT'
你是一位资深网文编辑，负责对一部长篇小说做"一致性审校"。请对照【小说大纲】、【已写章节进度】和【小说记忆】，找出需要作者处理的问题：

1. outline_drift 剧情偏离：写了大纲没有的重要剧情，或跳过了大纲计划的关键节点
2. contradiction 前后矛盾：人物、设定、时间线、物品状态自相矛盾
3. foreshadowing_dropped 伏笔遗忘：早期埋下的伏笔长期未回收，且按当前剧情走向已难以自然回收
4. character_inconsistency 人物失据：性格、能力、目标前后不一致
5. timeline_conflict 时间线冲突
6. other 其他问题

要求：
- 只报告真实存在的问题，不要为了凑数而报告，轻微的风格差异不算问题
- 每条问题给出 description（具体描述）、suggestion（可执行的修复建议）、related_chapters（相关章号数组）
- status 分级：ok 没有问题 / warning 存在需要关注的问题 / critical 存在会破坏作品一致性的严重问题
- 严格只输出 JSON（不要输出任何其他文字或代码块标记）：
{"status":"ok|warning|critical","summary":"总体评价，1~3 句","issues":[{"severity":"minor|major|critical","type":"outline_drift|contradiction|foreshadowing_dropped|character_inconsistency|timeline_conflict|other","description":"问题描述","suggestion":"修复建议","related_chapters":[章号]}]}
PROMPT,
            ],
            [
                'type' => 'chapter_continue',
                'name' => 'AI 续写',
                'description' => '基于全部上下文续写下一章',
                'content' => <<<'PROMPT'
你是一位资深网文作者，正在连载一部长篇小说。请根据下面提供的：小说设定、小说记忆（当前状态）、历史章节摘要、相关历史章节（检索召回）、最近章节正文、本章大纲，续写下一章。

写作要求：
- 本章约 {{target_words}} 字
- 严格延续已有设定与剧情，开头自然衔接最近一章的结尾
- 若提供了【相关历史章节（检索召回）】，这些是与本章剧情相关的早期章节，优先参考它们回收伏笔或衔接支线
- 推进主线或当前支线，制造新的冲突或悬念
- 结尾留钩子，吸引继续阅读
- 只输出正文内容本身：不要输出章节标题、不要写"第X章"、不要写"本章完"、不要任何解释
PROMPT,
            ],
        ];

        foreach ($defaults as $item) {
            if (Prompt::where('type', $item['type'])->exists()) {
                continue;
            }
            $prompt = new Prompt();
            $prompt->type = $item['type'];
            $prompt->name = $item['name'];
            $prompt->description = $item['description'];
            $prompt->content = trim($item['content']);
            $prompt->updated_at = now();
            $prompt->save();
        }

        // 升级仍是"旧版默认内容"的 Prompt（含指纹才替换，用户自定义修改会被保留）
        $upgradableTypes = array_column($defaults, 'type');
        foreach ($upgradableTypes as $type) {
            $prompt = Prompt::where('type', $type)->first();
            if (!$prompt) {
                continue;
            }
            $newItem = null;
            foreach ($defaults as $item) {
                if ($item['type'] === $type) {
                    $newItem = $item;
                    break;
                }
            }
            $isStockOld = match ($type) {
                'memory_update' => str_contains((string)$prompt->content, '"main_character"') && !str_contains((string)$prompt->content, '"schema"'),
                'chapter_generate', 'chapter_continue' => str_contains((string)$prompt->content, '历史章节摘要') && !str_contains((string)$prompt->content, '检索召回'),
                default => false,
            };
            if ($isStockOld && $newItem !== null) {
                $prompt->name = $newItem['name'];
                $prompt->description = $newItem['description'];
                $prompt->content = trim($newItem['content']);
                $prompt->updated_at = now();
                $prompt->save();
                $output->writeln("<comment>  已升级 Prompt：{$type}（旧版默认内容 → 新版）</comment>");
            }
        }
    }

    private function seedConfig(OutputInterface $output): void
    {
        $defaults = [
            'site_name' => ['AI 小说工坊', '站点名称'],
            'ai_temperature' => ['0.8', 'AI 默认温度'],
            'ai_http_timeout' => ['120', 'AI HTTP 超时（秒）'],
            'context_max_recent_chapters' => ['5', '上下文携带的最近章节正文数量'],
            'context_summary_max_chars' => ['12000', '上下文历史摘要字符上限'],
            'chapter_target_words' => ['3000', '每章目标字数'],
            'outline_volumes' => ['3', '大纲默认卷数'],
            'outline_chapters_per_volume' => ['20', '大纲每卷章数'],
            'retrieval_enabled' => ['1', '启用相关章节检索（生成时按相关性召回历史摘要，0=关闭）'],
            'retrieval_max_chapters' => ['5', '每章生成时召回的相关历史章节数量'],
            'consistency_auto_interval' => ['0', '每 N 章自动运行一致性审校（0=关闭）'],
        ];
        $existing = SystemConfig::allAsMap();
        $toSet = [];
        foreach ($defaults as $key => [$value, $desc]) {
            if (!array_key_exists($key, $existing)) {
                $toSet[$key] = $value;
            }
            // 描述始终补齐
            $row = SystemConfig::where('key', $key)->first();
            if ($row && $row->description === '') {
                $row->description = $desc;
                $row->save();
            }
        }
        if ($toSet !== []) {
            SystemConfig::set($toSet);
        }
    }
}

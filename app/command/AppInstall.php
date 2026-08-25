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
你是一位资深网文作者，正在创作长篇小说。请根据下面提供的：小说设定、小说记忆（当前状态）、历史章节摘要、最近章节正文、本章大纲，撰写本章正文。

写作要求：
- 本章约 {{target_words}} 字
- 文风为网文风格：节奏明快、对话与描写均衡、爽点安排合理
- 严格延续已有设定、人物性格与剧情逻辑，绝不与历史章节矛盾
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
                'description' => '旧记忆 + 最新章 → 整理出新记忆（JSON）',
                'content' => <<<'PROMPT'
你负责维护一部长篇小说的"小说记忆"。小说记忆是 AI 续写时了解"故事当前状态"的权威数据。

请根据【旧记忆】和【最新一章】更新记忆：
- 旧记忆里仍然有效的信息必须保留（合并，而不是重写）
- 只记录"当前有效状态"：已解决的旧问题可以移除，但要体现剧情推进
- 新增本章带来的变化：人物状态、地点、时间线、新人物、新物品、新伏笔、未解决问题
- 内容必须精简、准确，避免冗余描述

严格只输出 JSON（不要输出任何其他文字），结构如下：
{
  "current_location": "当前地点",
  "current_time": "当前时间线",
  "main_character": {"name": "主角名", "realm": "当前境界/实力", "status": "当前身份与状态"},
  "main_plot": "当前主线进展（1~2 句）",
  "unresolved_events": ["未解决的问题或任务"],
  "foreshadowing": ["已埋下尚未回收的伏笔"],
  "relationships": ["重要人物关系变化"],
  "important_items": ["重要物品及其状态"],
  "side_plots": ["支线进展"]
}
PROMPT,
            ],
            [
                'type' => 'chapter_continue',
                'name' => 'AI 续写',
                'description' => '基于全部上下文续写下一章',
                'content' => <<<'PROMPT'
你是一位资深网文作者，正在连载一部长篇小说。请根据下面提供的：小说设定、小说记忆（当前状态）、历史章节摘要、最近章节正文、本章大纲，续写下一章。

写作要求：
- 本章约 {{target_words}} 字
- 严格延续已有设定与剧情，开头自然衔接最近一章的结尾
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

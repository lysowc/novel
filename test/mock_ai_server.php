<?php
/**
 * Mock OpenAI-Compatible 服务（仅用于本地开发/联调测试）
 * 用法: php -S 127.0.0.1:8899 test/mock_ai_server.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_ends_with($path, '/chat/completions')) {
    $body = json_decode(file_get_contents('php://input'), true);
    $messages = $body['messages'] ?? [];
    $stream = !empty($body['stream']);

    // 合并全部文本用于识别场景
    $all = '';
    foreach ($messages as $m) {
        $all .= ($m['content'] ?? '') . "\n";
    }

    [$scenario, $reply, $chunkSize] = detectScenario($all);

    $usage = ['prompt_tokens' => 800, 'completion_tokens' => 400, 'total_tokens' => 1200];

    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        $chunks = mb_str_split($reply, $chunkSize);
        foreach ($chunks as $chunk) {
            $event = [
                'id' => 'chatcmpl-mock-' . uniqid(),
                'object' => 'chat.completion.chunk',
                'model' => $body['model'] ?? 'mock',
                'choices' => [[
                    'index' => 0,
                    'delta' => ['content' => $chunk],
                    'finish_reason' => null,
                ]],
            ];
            echo "data: " . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            usleep(30000);
        }
        // 结束 chunk
        $final = [
            'id' => 'chatcmpl-mock-' . uniqid(),
            'object' => 'chat.completion.chunk',
            'model' => $body['model'] ?? 'mock',
            'choices' => [[
                'index' => 0,
                'delta' => [],
                'finish_reason' => 'stop',
            ]],
            'usage' => $usage,
        ];
        echo "data: " . json_encode($final, JSON_UNESCAPED_UNICODE) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'id' => 'chatcmpl-mock-' . uniqid(),
        'object' => 'chat.completion',
        'model' => $body['model'] ?? 'mock',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $reply],
            'finish_reason' => 'stop',
        ]],
        'usage' => $usage,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => ['message' => 'not found']]);

/**
 * 根据 Prompt 特征识别场景并生成对应回复
 */
function detectScenario(string $all): array
{
    // 点子保存提炼
    if (str_contains($all, '提炼出小说点子')) {
        return ['idea_save', "测试之书：凡人逆天\n主角林凡本是普通山村少年，意外获得一枚神秘古戒，从此踏上修行之路。世界观为修仙世界，宗门林立、万族争锋。核心卖点是稳健发育与扮猪吃虎。", 10];
    }
    // 小说设定
    if (str_contains($all, '生成完整的基础设定') || str_contains($all, '输出完整的小说设定')) {
        return ['novel_setting', mockSetting(), 15];
    }
    // 大纲
    if (str_contains($all, '分卷章节大纲')) {
        return ['outline', mockOutline(), 15];
    }
    // 一致性审校
    if (str_contains($all, '一致性审校')) {
        return ['consistency', mockConsistencyReport(), 12];
    }
    // 章节摘要
    if (str_contains($all, '章节摘要') && str_contains($all, '本章正文')) {
        return ['summary', mockSummary(), 12];
    }
    // 记忆更新
    if (str_contains($all, '小说记忆') && str_contains($all, '旧记忆')) {
        return ['memory', mockMemory(), 12];
    }
    // 章节正文 / 续写
    if (str_contains($all, '撰写本章正文') || str_contains($all, '续写下一章')) {
        return ['chapter', mockChapter($all), 8];
    }
    // 点子聊天
    if (str_contains($all, '网文策划编辑')) {
        return ['idea_chat', "这个想法很有潜力！我想确认几个关键点：\n1. 主角的金手指具体是什么？\n2. 世界的核心冲突是什么？\n3. 你希望故事的爽点节奏偏快还是稳健发育？", 6];
    }
    return ['unknown', "（mock AI 回复）已收到。", 8];
}

function mockSetting(): string
{
    return <<<'TEXT'
【小说简介】
山村少年林凡偶然获得一枚上古青铜戒，从此踏上逆天修行路。宗门倾轧、万族争锋，他凭借神秘古戒中的传承与智慧，一步步揭开盘古大陆的惊天秘密，最终问鼎巅峰。

【世界观】
盘古大陆，天地灵气浓郁，修士可修五境：炼气、筑基、金丹、元婴、化神。宗门林立，正道以青云宗为首，魔道以血魔宗为尊。大陆每三百年开启一次天元秘境，蕴含飞升之秘。古戒中封印着上古传承与一缕残魂。

【主要人物设定】
林凡（主角）：16岁山村少年，坚毅果敢，外冷内热。金手指：上古青铜戒，内含炼体功法与残缺传承。
苏瑶：青云宗内门天才少女，清冷孤傲，与林凡相识于秘境。
王腾：血魔宗少主，心狠手辣，因古戒与林凡结怨。
老村长：林凡的启蒙者，隐藏身份的神秘强者。

【主要势力】
青云宗：正道之首，剑道传承，维护大陆秩序。
血魔宗：魔道巨擘，嗜血功法，觊觎古戒传承。
丹霞谷：中立炼丹势力，富可敌国。

【核心冲突】
林凡身怀古戒传承，被血魔宗追杀；同时青云宗内部派系斗争暗流涌动。他要在夹缝中求生，并揭开古戒与天元秘境的联系。

【故事主线】
第一阶段：山村少年获得古戒，初入修行，卷入夺戒风波。
第二阶段：拜入青云宗，站稳脚跟，成为内门弟子。
第三阶段：天元秘境开启，揭开古戒秘密，与血魔宗正面交锋。
第四阶段：大陆动荡，林凡率众对抗血魔宗，飞升在即。

【文风要求】
热血爽文风格，节奏明快，单章 2500~3500 字，视角以主角为主，对话简洁有力，战斗场面热血，成长线清晰。
TEXT;
}

function mockOutline(): string
{
    $volumes = [];
    for ($v = 1; $v <= 3; $v++) {
        $chapters = [];
        for ($c = 1; $c <= 5; $c++) {
            $no = ($v - 1) * 5 + $c;
            $chapters[] = [
                'no' => $no,
                'title' => "第{$no}章 试炼之路",
                'summary' => "林凡在第{$no}章中遭遇新挑战，突破自身极限，并获得关键线索。",
            ];
        }
        $volumes[] = ['title' => "第{$v}卷 风云初起", 'chapters' => $chapters];
    }
    return json_encode(['volumes' => $volumes], JSON_UNESCAPED_UNICODE);
}

function mockSummary(): string
{
    return '林凡进入青云宗外门，在入门试炼中击败王涛，获得长老认可并得到《青元诀》。王涛因此记恨林凡。林凡发现试炼中的妖兽异常，怀疑有人暗中操控。本章结束时，林凡正式成为青云宗外门弟子，并决定调查妖兽异常的原因。';
}

function mockMemory(): string
{
    return json_encode([
        'schema' => 'v2',
        'current_state' => [
            'location' => '青云宗外门',
            'time' => '拜入宗门后第三个月',
            'plot_progress' => '林凡站稳外门并成为传功长老记名弟子，正在调查试炼妖兽异常，为进入内门做准备。',
        ],
        'characters' => [
            ['name' => '林凡', 'status' => '炼气九层，传功长老记名弟子', 'relationships' => '与苏瑶初次相遇，互相留意；与王涛结怨', 'goals' => '查清妖兽异常真相，进入内门'],
            ['name' => '王涛', 'status' => '外门弟子，试炼落败', 'relationships' => '与林凡结怨', 'goals' => '报复林凡'],
        ],
        'foreshadowing' => [
            ['description' => '古戒中的残魂即将苏醒', 'planted_chapter' => 1, 'status' => 'open', 'resolved_chapter' => 0],
        ],
        'world_facts' => ['试炼妖兽异常，疑似有人暗中操控'],
        'timeline' => [
            ['chapter' => 1, 'event' => '林凡获得上古青铜戒，踏上修行路'],
            ['chapter' => 2, 'event' => '拜入青云宗，成为外门弟子'],
        ],
        'unresolved_events' => ['调查妖兽异常的原因', '王涛可能的报复'],
        'important_items' => [
            ['name' => '上古青铜戒', 'status' => '已认主，传承尚未完全开启'],
            ['name' => '《青元诀》', 'status' => '入门功法，已小成'],
        ],
        'style_notes' => '热血爽文风格，节奏明快',
    ], JSON_UNESCAPED_UNICODE);
}

function mockConsistencyReport(): string
{
    return json_encode([
        'status' => 'warning',
        'summary' => '整体推进基本符合大纲，但存在两处需要关注的伏笔与时间线问题。',
        'issues' => [
            [
                'severity' => 'major',
                'type' => 'foreshadowing_dropped',
                'description' => '第3章埋下的"古戒残魂"伏笔在后续章节中未被提及，且当前剧情已进入宗门线，原设定的回收节点可能已被错过。',
                'suggestion' => '在后续章节安排一次古戒异动或梦境，重新激活该伏笔。',
                'related_chapters' => [3],
            ],
            [
                'severity' => 'minor',
                'type' => 'timeline_conflict',
                'description' => '第6章提到"入门两月"，与第5章的"入门三月"时间表述不一致。',
                'suggestion' => '统一时间表述，建议以"入门三月"为准。',
                'related_chapters' => [5, 6],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function mockChapter(string $all): string
{
    // 从上下文中提取章节号用于正文衔接
    preg_match('/第(\d+)章/', $all, $m);
    $no = $m[1] ?? '1';
    return "晨光初现，青云宗外门的练武场上已经站满了人。\n\n林凡深吸一口气，缓缓睁开双眼。经过三个月的苦修，《青元诀》终于小成，体内的灵气比初入宗门时浑厚了数倍。\n\n“林凡，长老传你过去。”一名师兄快步走来，压低声音说道。\n\n林凡点了点头，跟在师兄身后，心中却暗自警惕。试炼中那妖兽的异常，一直让他难以释怀。\n\n大殿之上，传功长老抚须而笑：“你入门三月，修为已至炼气九层，悟性惊人。老夫有意收你为记名弟子，你可愿意？”\n\n林凡抱拳躬身：“弟子愿意。”\n\n话音未落，一道阴冷的目光从殿外投来。王涛站在廊下，拳头攥得发白。\n\n林凡不动声色，心中默念：妖兽之事，今日必须查个水落石出。\n\n夜色降临，他悄悄潜往后山。那里的妖兽禁地，正是试炼时异变的源头……";
}

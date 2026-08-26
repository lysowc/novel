<?php

namespace app\controller\admin;

use app\model\Category;
use app\model\Idea;
use app\model\IdeaChat;
use app\model\Novel;
use app\service\AiClient;
use app\service\AiTaskService;
use app\service\PromptService;
use support\Request;
use Webman\Http\Response;
use Workerman\Protocols\Http\ServerSentEvents;

class IdeaController
{
    public function index(Request $request)
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 10)));
        $status = trim((string)$request->get('status', ''));
        $categoryId = (int)$request->get('category_id', 0);

        $query = Idea::query()->with('category');
        if ($status !== '' && in_array($status, ['unused', 'used'], true)) {
            $query->where('status', $status);
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        $total = (clone $query)->count();
        $list = $query->orderByDesc('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get()
            ->map(fn (Idea $idea) => $this->format($idea));

        return ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function store(Request $request)
    {
        $title = trim((string)$request->post('title', ''));
        if ($title === '') {
            return fail('点子标题不能为空');
        }
        $idea = new Idea();
        $idea->category_id = (int)$request->post('category_id', 0) ?: null;
        $idea->title = $title;
        $idea->content = trim((string)$request->post('content', ''));
        $idea->status = 'unused';
        $idea->save();
        return ok($this->format($idea), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        if ($request->post('title') !== null) {
            $idea->title = trim((string)$request->post('title')) ?: $idea->title;
        }
        if ($request->post('content') !== null) {
            $idea->content = (string)$request->post('content');
        }
        if ($request->post('category_id') !== null) {
            $idea->category_id = (int)$request->post('category_id') ?: null;
        }
        $idea->save();
        return ok($this->format($idea), '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        IdeaChat::where('idea_id', $id)->delete();
        $idea->delete();
        return ok(null, '已删除');
    }

    public function messages(Request $request, int $id)
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        $list = IdeaChat::where('idea_id', $id)->orderBy('id')->get()->map(function (IdeaChat $chat) {
            return ['id' => $chat->id, 'role' => $chat->role, 'content' => $chat->content, 'created_at' => $chat->created_at];
        });
        return ok($list);
    }

    /**
     * 点子聊天（SSE 流式，同步直连 AI）
     */
    public function chat(Request $request, int $id): Response
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        $message = trim((string)$request->post('message', ''));
        if ($message === '') {
            return fail('消息不能为空');
        }

        // 保存用户消息
        IdeaChat::create(['idea_id' => $id, 'role' => 'user', 'content' => $message, 'created_at' => now()]);

        $connection = $request->connection;
        $connection->send(new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ], ''));

        $send = function (array $event) use ($connection) {
            $connection->send(new ServerSentEvents([
                'data' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]));
        };

        // 组装消息历史
        $history = IdeaChat::where('idea_id', $id)->orderBy('id')->get();
        $all = $history->map(fn (IdeaChat $chat) => ['role' => $chat->role, 'content' => $chat->content])->values();

        // 上下文压缩：≤22 条全带；否则保留最早 2 条（种子话题）+ 最近 20 条；
        // 再按总字符量（3 万字）从尾部倒序兜底截断，防止顶穿模型上下文窗口
        if ($all->count() <= 22) {
            $messages = $all->toArray();
        } else {
            $messages = array_merge($all->slice(0, 2)->values()->toArray(), $all->slice(-20)->values()->toArray());
        }
        $total = 0;
        $trimmed = [];
        foreach (array_reverse($messages) as $m) {
            $len = mb_strlen($m['content']);
            if ($total + $len > 30000 && $trimmed !== []) {
                break;
            }
            $trimmed[] = $m;
            $total += $len;
        }
        $messages = array_reverse($trimmed);
        $omitted = $all->count() - count($messages);
        if ($omitted > 0) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => "（注意：更早的 {$omitted} 条讨论记录因长度限制已省略，请基于现有上下文继续讨论，必要时先向作者确认此前确定的关键设定。）",
            ]);
        }

        // 附上点子背景，帮助 AI 保持讨论上下文
        $category = $idea->category ? $idea->category->name : '';
        $background = "这是关于一部小说的点子讨论。";
        if ($category !== '') {
            $background .= "题材方向：{$category}。";
        }
        if (trim((string)$idea->content) !== '') {
            $background .= "\n当前已记录的点子草稿：{$idea->content}";
        }
        array_unshift($messages, ['role' => 'system', 'content' => PromptService::render('idea_chat', []) . "\n\n{$background}"]);

        try {
            $full = '';
            (new AiClient())->chatStream(
                $messages,
                ['task_type' => 'idea_chat'],
                function (string $delta) use (&$full, $send) {
                    $full .= $delta;
                    $send(['type' => 'delta', 'content' => $delta]);
                }
            );
            $full = trim($full);
            if ($full !== '') {
                IdeaChat::create(['idea_id' => $id, 'role' => 'assistant', 'content' => $full, 'created_at' => now()]);
            }
            $send(['type' => 'done']);
        } catch (\Throwable $e) {
            $send(['type' => 'error', 'msg' => $e->getMessage()]);
        }
        $connection->close();
        return new Response(200); // 占位，实际已通过 connection 发送
    }

    /**
     * 保存点子：AI 提炼标题与内容（失败时降级）
     */
    public function save(Request $request, int $id)
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        if ($idea->status === 'used') {
            return fail('该点子已生成小说，不能修改');
        }

        $chats = IdeaChat::where('idea_id', $id)->orderBy('id')->get();
        if ($chats->isEmpty()) {
            return fail('还没有聊天内容');
        }
        $transcript = $chats->map(fn (IdeaChat $chat) => ($chat->role === 'user' ? '作者：' : 'AI：') . $chat->content)->implode("\n");
        // 提炼时兜底截断：头 3000 字 + 尾 12000 字
        if (mb_strlen($transcript) > 15000) {
            $transcript = mb_substr($transcript, 0, 3000)
                . "\n……（中间讨论省略）……\n"
                . mb_substr($transcript, -12000);
        }

        try {
            $system = "你是一位网文策划编辑。请根据下面的讨论记录，提炼出小说点子。\n"
                . "严格只输出两行：\n"
                . "第一行：标题（20 字以内）\n"
                . "第二行：点子内容（200~400 字，包含核心创意、主角设定、世界观、卖点）";
            $result = (new AiClient())->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "讨论记录：\n{$transcript}"],
            ], ['task_type' => 'idea_save']);
            $lines = array_values(array_filter(array_map('trim', explode("\n", trim($result['text'])))));
            $title = $lines[0] ?? '';
            $content = implode("\n", array_slice($lines, 1));
            // 去掉可能的"标题："前缀
            $title = preg_replace('/^(标题|书名)[:：]\s*/u', '', $title);
        } catch (\Throwable) {
            $title = '';
            $content = '';
        }

        if ($title === '' || $content === '') {
            // 降级：取最后一条用户消息
            $lastUser = $chats->where('role', 'user')->last();
            $fallback = $lastUser ? trim((string)$lastUser->content) : '';
            if ($title === '') {
                $title = mb_substr($fallback, 0, 20) ?: '未命名点子';
            }
            if ($content === '') {
                $content = $fallback ?: $transcript;
            }
        }

        $idea->title = $title;
        $idea->content = $content;
        $idea->save();
        return ok($this->format($idea), '点子已保存');
    }

    /**
     * 根据点子创建小说（入队生成设定任务）
     */
    public function createNovel(Request $request, int $id)
    {
        $idea = Idea::find($id);
        if (!$idea) {
            return fail('点子不存在');
        }
        if ($idea->status === 'used' || $idea->novel_id) {
            return fail('该点子已生成过小说');
        }

        $novel = new Novel();
        $novel->category_id = $idea->category_id;
        $novel->title = $idea->title;
        $novel->cover = '';
        $novel->description = (string)$idea->content;
        $novel->tags = '';
        $novel->status = 'draft';
        $novel->is_public = 0;
        $novel->save();

        $idea->novel_id = $novel->id;
        $idea->status = 'used';
        $idea->save();

        $task = AiTaskService::create('generate_setting', $novel->id);
        return ok(['novel_id' => $novel->id, 'task_id' => $task->id], '小说已创建，AI 正在生成设定');
    }

    private function format(Idea $idea): array
    {
        return [
            'id' => $idea->id,
            'category_id' => $idea->category_id,
            'category_name' => $idea->category->name ?? '',
            'title' => $idea->title,
            'content' => $idea->content,
            'status' => $idea->status,
            'novel_id' => $idea->novel_id,
            'created_at' => $idea->created_at,
            'updated_at' => $idea->updated_at,
        ];
    }
}

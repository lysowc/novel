<?php

namespace app\controller\admin;

use app\model\Chapter;
use app\model\Novel;
use app\model\NovelMemory;
use app\model\NovelSetting;
use support\Request;

class NovelController
{
    public function index(Request $request)
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 10)));
        $keyword = trim((string)$request->get('keyword', ''));
        $categoryId = (int)$request->get('category_id', 0);
        $status = trim((string)$request->get('status', ''));

        $query = Novel::query()->with('category');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($status !== '' && in_array($status, ['draft', 'published', 'finished'], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $list = $query->orderByDesc('updated_at')->orderByDesc('id')
            ->offset(($page - 1) * $pageSize)->limit($pageSize)->get()
            ->map(fn (Novel $novel) => $this->format($novel));

        return ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function show(Request $request, int $id)
    {
        $novel = Novel::with('category')->find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        return ok($this->format($novel));
    }

    public function store(Request $request)
    {
        $title = trim((string)$request->post('title', ''));
        if ($title === '') {
            return fail('书名不能为空');
        }
        $novel = new Novel();
        $novel->title = $title;
        $novel->category_id = (int)$request->post('category_id', 0) ?: null;
        $novel->cover = trim((string)$request->post('cover', ''));
        $novel->description = trim((string)$request->post('description', ''));
        $novel->tags = trim((string)$request->post('tags', ''));
        $novel->status = in_array($request->post('status', 'draft'), ['draft', 'published', 'finished'], true)
            ? $request->post('status', 'draft') : 'draft';
        $novel->is_public = (int)(bool)$request->post('is_public', 0);
        $novel->save();
        return ok($this->format($novel), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        $title = trim((string)$request->post('title', $novel->title));
        if ($title === '') {
            return fail('书名不能为空');
        }
        $novel->title = $title;
        if ($request->post('category_id') !== null) {
            $novel->category_id = (int)$request->post('category_id') ?: null;
        }
        if ($request->post('cover') !== null) {
            $novel->cover = trim((string)$request->post('cover', ''));
        }
        if ($request->post('description') !== null) {
            $novel->description = trim((string)$request->post('description', ''));
        }
        if ($request->post('tags') !== null) {
            $novel->tags = trim((string)$request->post('tags', ''));
        }
        if ($request->post('status') !== null && in_array($request->post('status'), ['draft', 'published', 'finished'], true)) {
            $novel->status = $request->post('status');
        }
        if ($request->post('is_public') !== null) {
            $novel->is_public = (int)(bool)$request->post('is_public');
        }
        $novel->save();
        return ok($this->format($novel), '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        Chapter::where('novel_id', $id)->delete();
        NovelSetting::where('novel_id', $id)->delete();
        NovelMemory::where('novel_id', $id)->delete();
        $novel->delete();
        return ok(null, '已删除');
    }

    /**
     * 后台章节列表（含摘要）
     */
    public function chapters(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        $list = Chapter::where('novel_id', $id)->orderBy('chapter_no')->get()->map(function (Chapter $chapter) {
            return [
                'id' => $chapter->id,
                'novel_id' => $chapter->novel_id,
                'chapter_no' => $chapter->chapter_no,
                'title' => $chapter->title,
                'summary' => $chapter->summary,
                'word_count' => $chapter->word_count,
                'status' => $chapter->status,
                'created_at' => $chapter->created_at,
                'updated_at' => $chapter->updated_at,
            ];
        });
        return ok(['list' => $list]);
    }

    /**
     * 手动新增章节
     */
    public function chapterStore(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        $title = trim((string)$request->post('title', ''));
        if ($title === '') {
            return fail('章节标题不能为空');
        }
        $chapterNo = (int)Chapter::where('novel_id', $id)->max('chapter_no') + 1;
        $chapter = new Chapter();
        $chapter->novel_id = $id;
        $chapter->chapter_no = $chapterNo;
        $chapter->title = $title;
        $chapter->summary = trim((string)$request->post('summary', ''));
        $chapter->content = (string)$request->post('content', '');
        $chapter->word_count = word_count($chapter->content);
        $chapter->status = 'published';
        $chapter->save();
        $novel->recount();
        return ok($chapter, '章节已添加');
    }

    public function setting(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        if ($request->method() === 'PUT') {
            $setting = NovelSetting::where('novel_id', $id)->first() ?? new NovelSetting(['novel_id' => $id]);
            foreach (['world_view', 'characters', 'factions', 'conflicts', 'main_plot', 'style'] as $field) {
                if ($request->post($field) !== null) {
                    $setting->{$field} = (string)$request->post($field);
                }
            }
            $setting->save();
            return ok($setting, '设定已保存');
        }
        $setting = NovelSetting::where('novel_id', $id)->first();
        return ok($setting ?: ['novel_id' => $id, 'world_view' => '', 'characters' => '', 'factions' => '', 'conflicts' => '', 'main_plot' => '', 'style' => '']);
    }

    public function memory(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        if ($request->method() === 'PUT') {
            $content = (string)$request->post('content', '');
            if (trim($content) !== '') {
                $data = json_decode($content, true);
                if (!is_array($data)) {
                    return fail('记忆必须是合法 JSON');
                }
            }
            $memory = NovelMemory::of($novel);
            $memory->content = $content;
            $memory->updated_at = now();
            $memory->save();
            return ok(['content' => $content, 'updated_at' => $memory->updated_at], '记忆已保存');
        }
        $memory = NovelMemory::of($novel);
        return ok(['content' => $memory->content ?? '', 'updated_at' => $memory->updated_at]);
    }

    public function outline(Request $request, int $id)
    {
        $novel = Novel::find($id);
        if (!$novel) {
            return fail('小说不存在');
        }
        if ($request->method() === 'PUT') {
            $outline = (string)$request->post('outline', '');
            if (trim($outline) !== '') {
                $data = json_decode($outline, true);
                if (!is_array($data) || !isset($data['volumes'])) {
                    return fail('大纲 JSON 格式错误（需要 {"volumes":[...]}）');
                }
            }
            $novel->outline = $outline;
            $novel->save();
            return ok(['outline' => $outline], '大纲已保存');
        }
        return ok(['outline' => $novel->outline ?? '']);
    }

    private function format(Novel $novel): array
    {
        return [
            'id' => $novel->id,
            'category_id' => $novel->category_id,
            'category_name' => $novel->category->name ?? '',
            'title' => $novel->title,
            'cover' => $novel->cover,
            'description' => $novel->description,
            'tags' => $novel->tags,
            'status' => $novel->status,
            'status_text' => $novel->status_text,
            'is_public' => $novel->is_public,
            'word_count' => $novel->word_count,
            'chapter_count' => $novel->chapter_count,
            'created_at' => $novel->created_at,
            'updated_at' => $novel->updated_at,
        ];
    }
}

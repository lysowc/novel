<?php

namespace app\controller\api;

use app\model\Chapter;
use app\model\Novel;
use support\Request;

class FrontNovelController
{
    public function index(Request $request)
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(60, max(1, (int)$request->get('page_size', 12)));
        $categoryId = (int)$request->get('category_id', 0);
        $keyword = trim((string)$request->get('keyword', ''));

        $query = Novel::with('category')
            ->where('is_public', 1)
            ->whereIn('status', ['published', 'finished']);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        $total = (clone $query)->count();
        $list = $query->orderByDesc('updated_at')->orderByDesc('id')
            ->offset(($page - 1) * $pageSize)->limit($pageSize)->get()
            ->map(fn (Novel $novel) => $this->format($novel));

        return ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    public function show(Request $request, int $id)
    {
        $novel = Novel::with('category')
            ->where('id', $id)
            ->where('is_public', 1)
            ->whereIn('status', ['published', 'finished'])
            ->first();
        if (!$novel) {
            return fail('小说不存在或未公开', 404);
        }
        $firstNo = Chapter::where('novel_id', $id)->orderBy('chapter_no')->value('chapter_no');
        $data = $this->format($novel);
        $data['first_no'] = $firstNo ? (int)$firstNo : null;
        return ok($data);
    }

    public function chapters(Request $request, int $id)
    {
        $novel = Novel::where('id', $id)->where('is_public', 1)->whereIn('status', ['published', 'finished'])->first();
        if (!$novel) {
            return fail('小说不存在或未公开', 404);
        }
        $list = Chapter::where('novel_id', $id)
            ->where('status', 'published')
            ->orderBy('chapter_no')
            ->get(['chapter_no', 'title', 'word_count', 'updated_at']);
        return ok(['list' => $list, 'total' => $list->count()]);
    }

    public function read(Request $request, int $id, int $no)
    {
        $novel = Novel::where('id', $id)->where('is_public', 1)->whereIn('status', ['published', 'finished'])->first();
        if (!$novel) {
            return fail('小说不存在或未公开', 404);
        }
        $chapter = Chapter::where('novel_id', $id)->where('chapter_no', $no)->where('status', 'published')->first();
        if (!$chapter) {
            return fail('章节不存在', 404);
        }
        $prevNo = Chapter::where('novel_id', $id)->where('status', 'published')
            ->where('chapter_no', '<', $no)->orderByDesc('chapter_no')->value('chapter_no');
        $nextNo = Chapter::where('novel_id', $id)->where('status', 'published')
            ->where('chapter_no', '>', $no)->orderBy('chapter_no')->value('chapter_no');

        return ok([
            'chapter' => [
                'id' => $chapter->id,
                'chapter_no' => $chapter->chapter_no,
                'title' => $chapter->title,
                'content' => $chapter->content,
                'word_count' => $chapter->word_count,
                'updated_at' => $chapter->updated_at,
            ],
            'novel' => ['id' => $novel->id, 'title' => $novel->title],
            'prev_no' => $prevNo ? (int)$prevNo : null,
            'next_no' => $nextNo ? (int)$nextNo : null,
        ]);
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
            'word_count' => $novel->word_count,
            'chapter_count' => $novel->chapter_count,
            'updated_at' => $novel->updated_at,
            'created_at' => $novel->created_at,
        ];
    }
}

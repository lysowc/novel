<?php

namespace app\controller\admin;

use app\model\Chapter;
use app\model\Novel;
use support\Request;

class ChapterController
{
    public function show(Request $request, int $id)
    {
        $chapter = Chapter::find($id);
        if (!$chapter) {
            return fail('章节不存在');
        }
        return ok([
            'id' => $chapter->id,
            'novel_id' => $chapter->novel_id,
            'chapter_no' => $chapter->chapter_no,
            'title' => $chapter->title,
            'summary' => $chapter->summary,
            'content' => $chapter->content,
            'word_count' => $chapter->word_count,
            'status' => $chapter->status,
            'created_at' => $chapter->created_at,
            'updated_at' => $chapter->updated_at,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $chapter = Chapter::find($id);
        if (!$chapter) {
            return fail('章节不存在');
        }
        if ($request->post('title') !== null) {
            $title = trim((string)$request->post('title'));
            if ($title === '') {
                return fail('章节标题不能为空');
            }
            $chapter->title = $title;
        }
        if ($request->post('summary') !== null) {
            $chapter->summary = (string)$request->post('summary');
        }
        if ($request->post('content') !== null) {
            $chapter->content = (string)$request->post('content');
            $chapter->word_count = word_count($chapter->content);
        }
        $chapter->save();
        if ($request->post('content') !== null) {
            Novel::find($chapter->novel_id)?->recount();
        }
        return ok($chapter, '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $chapter = Chapter::find($id);
        if (!$chapter) {
            return fail('章节不存在');
        }
        $novelId = $chapter->novel_id;
        $chapter->delete();
        // 章号不重排（大纲对齐），仅重算统计
        Novel::find($novelId)?->recount();
        return ok(null, '已删除');
    }
}

<?php

namespace app\controller\api;

use app\model\Category;
use app\model\Novel;
use app\model\SystemConfig;
use support\Request;

class HomeController
{
    public function home(Request $request)
    {
        $recentUpdates = Novel::with('category')
            ->where('is_public', 1)
            ->whereIn('status', ['published', 'finished'])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get()
            ->map(fn (Novel $novel) => $this->formatNovel($novel));

        $categories = $this->categoryList();

        return ok([
            'site_name' => SystemConfig::get('site_name', 'AI 小说工坊'),
            'recent_updates' => $recentUpdates,
            'categories' => $categories,
        ]);
    }

    public function categories(Request $request)
    {
        return ok(['list' => $this->categoryList()]);
    }

    private function categoryList(): array
    {
        return Category::where('status', 1)->orderBy('sort')->orderBy('id')->get()->map(function (Category $category) {
            $count = Novel::where('category_id', $category->id)
                ->where('is_public', 1)
                ->whereIn('status', ['published', 'finished'])
                ->count();
            if ($count === 0) {
                return null;
            }
            return ['id' => $category->id, 'name' => $category->name, 'novel_count' => $count];
        })->filter()->values()->toArray();
    }

    private function formatNovel(Novel $novel): array
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
        ];
    }
}

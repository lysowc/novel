<?php

namespace app\controller\admin;

use app\model\Category;
use app\model\Novel;
use support\Request;

class CategoryController
{
    public function index(Request $request)
    {
        $list = Category::orderBy('sort')->orderBy('id')->get()->map(function (Category $category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort' => $category->sort,
                'status' => $category->status,
                'novel_count' => Novel::where('category_id', $category->id)->count(),
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        });
        return ok(['list' => $list]);
    }

    public function store(Request $request)
    {
        $name = trim((string)$request->post('name', ''));
        if ($name === '') {
            return fail('分类名不能为空');
        }
        if (Category::where('name', $name)->exists()) {
            return fail('分类名已存在');
        }
        $category = new Category();
        $category->name = $name;
        $category->description = trim((string)$request->post('description', ''));
        $category->sort = (int)$request->post('sort', 0);
        $category->status = (int)(bool)$request->post('status', 1);
        $category->save();
        return ok($category, '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return fail('分类不存在');
        }
        $name = trim((string)$request->post('name', ''));
        if ($name === '') {
            return fail('分类名不能为空');
        }
        if (Category::where('name', $name)->where('id', '!=', $id)->exists()) {
            return fail('分类名已存在');
        }
        $category->name = $name;
        $category->description = trim((string)$request->post('description', ''));
        $category->sort = (int)$request->post('sort', $category->sort);
        $category->status = (int)(bool)$request->post('status', $category->status);
        $category->save();
        return ok($category, '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return fail('分类不存在');
        }
        if (Novel::where('category_id', $id)->exists()) {
            return fail('该分类下还有小说，无法删除');
        }
        $category->delete();
        return ok(null, '已删除');
    }
}

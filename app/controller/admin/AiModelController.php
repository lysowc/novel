<?php

namespace app\controller\admin;

use app\model\AiModel;
use app\model\AiProvider;
use support\Request;

class AiModelController
{
    public function index(Request $request)
    {
        $list = AiModel::with('provider')->orderByDesc('is_default')->orderBy('id')->get()->map(function (AiModel $model) {
            return [
                'id' => $model->id,
                'provider_id' => $model->provider_id,
                'provider_name' => $model->provider->name ?? '',
                'name' => $model->name,
                'display_name' => $model->display_name,
                'max_tokens' => $model->max_tokens,
                'temperature' => $model->temperature,
                'status' => $model->status,
                'is_default' => $model->is_default,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
            ];
        });
        return ok($list);
    }

    public function store(Request $request)
    {
        $providerId = (int)$request->post('provider_id', 0);
        $name = trim((string)$request->post('name', ''));
        if (!$providerId || !AiProvider::where('id', $providerId)->exists()) {
            return fail('请选择 Provider');
        }
        if ($name === '') {
            return fail('模型名不能为空');
        }
        $model = new AiModel();
        $model->provider_id = $providerId;
        $model->name = $name;
        $model->display_name = trim((string)$request->post('display_name', $name));
        $model->max_tokens = (int)$request->post('max_tokens', 0) ?: null;
        $model->temperature = $request->post('temperature') !== null && $request->post('temperature') !== ''
            ? (float)$request->post('temperature') : null;
        $model->status = (int)(bool)$request->post('status', 1);
        $model->is_default = AiModel::count() === 0 ? 1 : 0;
        $model->save();
        return ok($model, '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $model = AiModel::find($id);
        if (!$model) {
            return fail('模型不存在');
        }
        if ($request->post('provider_id') !== null) {
            $providerId = (int)$request->post('provider_id');
            if (!AiProvider::where('id', $providerId)->exists()) {
                return fail('Provider 不存在');
            }
            $model->provider_id = $providerId;
        }
        if ($request->post('name') !== null) {
            $model->name = trim((string)$request->post('name')) ?: $model->name;
        }
        if ($request->post('display_name') !== null) {
            $model->display_name = trim((string)$request->post('display_name'));
        }
        if ($request->post('max_tokens') !== null) {
            $model->max_tokens = (int)$request->post('max_tokens') ?: null;
        }
        if ($request->post('temperature') !== null) {
            $model->temperature = $request->post('temperature') === '' ? null : (float)$request->post('temperature');
        }
        if ($request->post('status') !== null) {
            $model->status = (int)(bool)$request->post('status');
        }
        $model->save();
        return ok($model, '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $model = AiModel::find($id);
        if (!$model) {
            return fail('模型不存在');
        }
        $model->delete();
        return ok(null, '已删除');
    }

    public function setDefault(Request $request, int $id)
    {
        $model = AiModel::find($id);
        if (!$model) {
            return fail('模型不存在');
        }
        AiModel::query()->update(['is_default' => 0]);
        $model->is_default = 1;
        $model->save();
        return ok(null, '已设为默认');
    }
}

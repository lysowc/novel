<?php

namespace app\controller\admin;

use app\model\AiModel;
use app\model\AiProvider;
use support\Request;

class AiProviderController
{
    public function index(Request $request)
    {
        $list = AiProvider::orderByDesc('is_default')->orderBy('id')->get()->map(function (AiProvider $provider) {
            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'base_url' => $provider->base_url,
                'api_key' => $provider->masked_key,
                'status' => $provider->status,
                'is_default' => $provider->is_default,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ];
        });
        return ok($list);
    }

    public function store(Request $request)
    {
        $name = trim((string)$request->post('name', ''));
        $baseUrl = trim((string)$request->post('base_url', ''));
        if ($name === '' || $baseUrl === '') {
            return fail('名称和 base_url 不能为空');
        }
        $provider = new AiProvider();
        $provider->name = $name;
        $provider->base_url = $baseUrl;
        $provider->api_key = trim((string)$request->post('api_key', ''));
        $provider->status = (int)(bool)$request->post('status', 1);
        $provider->is_default = AiProvider::count() === 0 ? 1 : 0;
        $provider->save();
        return ok(['id' => $provider->id, 'api_key' => $provider->masked_key], '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $provider = AiProvider::find($id);
        if (!$provider) {
            return fail('Provider 不存在');
        }
        if ($request->post('name') !== null) {
            $provider->name = trim((string)$request->post('name')) ?: $provider->name;
        }
        if ($request->post('base_url') !== null) {
            $provider->base_url = trim((string)$request->post('base_url')) ?: $provider->base_url;
        }
        if ($request->post('api_key') !== null && trim((string)$request->post('api_key')) !== '') {
            // 留空 = 不变更
            $provider->api_key = trim((string)$request->post('api_key'));
        }
        if ($request->post('status') !== null) {
            $provider->status = (int)(bool)$request->post('status');
        }
        $provider->save();
        return ok(['id' => $provider->id, 'api_key' => $provider->masked_key], '已保存');
    }

    public function destroy(Request $request, int $id)
    {
        $provider = AiProvider::find($id);
        if (!$provider) {
            return fail('Provider 不存在');
        }
        if (AiModel::where('provider_id', $id)->exists()) {
            return fail('该 Provider 下还有模型，请先删除模型');
        }
        $provider->delete();
        return ok(null, '已删除');
    }

    public function setDefault(Request $request, int $id)
    {
        $provider = AiProvider::find($id);
        if (!$provider) {
            return fail('Provider 不存在');
        }
        AiProvider::query()->update(['is_default' => 0]);
        $provider->is_default = 1;
        $provider->save();
        return ok(null, '已设为默认');
    }
}

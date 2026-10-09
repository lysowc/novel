<?php

namespace app\controller\admin;

use app\model\SystemConfig;
use support\Request;

class ConfigController
{
    private const KEYS = [
        'site_name' => '站点名称',
        'ai_temperature' => 'AI 默认温度',
        'ai_http_timeout' => 'AI HTTP 超时（秒）',
        'context_max_recent_chapters' => '上下文最近章节数',
        'context_summary_max_chars' => '上下文摘要字符上限',
        'chapter_target_words' => '每章目标字数',
        'outline_volumes' => '大纲默认卷数',
        'outline_chapters_per_volume' => '大纲每卷章数',
        'retrieval_enabled' => '启用相关章节检索',
        'retrieval_max_chapters' => '每章召回的相关章节数',
        'consistency_auto_interval' => '自动审校间隔（章）',
    ];

    public function index(Request $request)
    {
        $map = SystemConfig::allAsMap();
        $result = [];
        foreach (self::KEYS as $key => $description) {
            $result[$key] = $map[$key] ?? self::defaultValue($key);
        }
        return ok($result);
    }

    public function update(Request $request)
    {
        $input = $request->post();
        if (!is_array($input)) {
            return fail('参数错误');
        }
        $values = [];
        foreach (self::KEYS as $key => $description) {
            if (array_key_exists($key, $input)) {
                $values[$key] = $input[$key];
            }
        }
        if ($values === []) {
            return fail('没有可保存的配置');
        }
        SystemConfig::set($values);
        return ok(null, '配置已保存');
    }

    private static function defaultValue(string $key): string
    {
        $defaults = [
            'site_name' => 'AI 小说工坊',
            'ai_temperature' => '0.8',
            'ai_http_timeout' => '120',
            'context_max_recent_chapters' => '5',
            'context_summary_max_chars' => '12000',
            'chapter_target_words' => '3000',
            'outline_volumes' => '3',
            'outline_chapters_per_volume' => '20',
            'retrieval_enabled' => '1',
            'retrieval_max_chapters' => '5',
            'consistency_auto_interval' => '0',
        ];
        return $defaults[$key] ?? '';
    }
}

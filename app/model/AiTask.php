<?php

namespace app\model;

use support\Model;

class AiTask extends Model
{
    protected $table = 'ai_task';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'params' => 'array',
    ];

    /**
     * 任务类型文案
     */
    public static function typeText(string $type): string
    {
        return [
            'generate_setting' => '生成设定',
            'generate_outline' => '生成大纲',
            'generate_chapter' => '生成章节',
            'continue_chapter' => 'AI 续写',
            'regenerate_chapter' => '重新生成',
            'generate_summary' => '生成摘要',
            'update_memory' => '更新记忆',
            'consistency_check' => '一致性审校',
        ][$type] ?? $type;
    }
}

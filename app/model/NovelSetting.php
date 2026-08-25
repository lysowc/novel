<?php

namespace app\model;

use support\Model;

class NovelSetting extends Model
{
    protected $table = 'novel_setting';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }

    /**
     * 设定转成给 AI 的文本
     */
    public function toContextText(): string
    {
        $map = [
            'world_view' => '世界观',
            'characters' => '主要人物设定',
            'factions' => '主要势力',
            'conflicts' => '核心冲突',
            'main_plot' => '故事主线',
            'style' => '文风要求',
        ];
        $parts = [];
        foreach ($map as $field => $label) {
            $value = trim((string)($this->{$field} ?? ''));
            if ($value !== '') {
                $parts[] = "【{$label}】\n{$value}";
            }
        }
        return implode("\n\n", $parts);
    }

    public function isEmpty(): bool
    {
        foreach (['world_view', 'characters', 'factions', 'conflicts', 'main_plot', 'style'] as $field) {
            if (trim((string)($this->{$field} ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }
}

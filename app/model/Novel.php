<?php

namespace app\model;

use support\Model;

class Novel extends Model
{
    protected $table = 'novel';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class, 'novel_id');
    }

    public function setting()
    {
        return $this->hasOne(NovelSetting::class, 'novel_id');
    }

    public function memory()
    {
        return $this->hasOne(NovelMemory::class, 'novel_id');
    }

    /**
     * 状态文案
     */
    public function getStatusTextAttribute(): string
    {
        return [
            'draft' => '创作中',
            'published' => '连载中',
            'finished' => '已完结',
        ][$this->status] ?? $this->status;
    }

    /**
     * 重新统计字数与章节数
     */
    public function recount(): void
    {
        $this->word_count = (int)Chapter::where('novel_id', $this->id)->sum('word_count');
        $this->chapter_count = (int)Chapter::where('novel_id', $this->id)->count();
        $this->save();
    }

    /**
     * 大纲数组（含卷结构）
     */
    public function getOutlineArray(): array
    {
        $data = json_decode($this->outline ?? '', true);
        return is_array($data) ? $data : ['volumes' => []];
    }

    public function setOutlineArray(array $data): void
    {
        $this->outline = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->save();
    }
}

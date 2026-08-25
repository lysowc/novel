<?php

namespace app\model;

use support\Model;

class NovelMemory extends Model
{
    protected $table = 'novel_memory';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }

    public static function of(Novel $novel): self
    {
        $memory = static::where('novel_id', $novel->id)->first();
        if (!$memory) {
            $memory = new static();
            $memory->novel_id = $novel->id;
            $memory->content = '';
        }
        return $memory;
    }
}

<?php

namespace app\model;

use support\Model;

class Chapter extends Model
{
    protected $table = 'chapter';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'chapter_no' => 'integer',
        'word_count' => 'integer',
    ];

    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }
}

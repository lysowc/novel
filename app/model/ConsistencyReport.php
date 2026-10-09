<?php

namespace app\model;

use support\Model;

/**
 * 一致性审校报告
 */
class ConsistencyReport extends Model
{
    protected $table = 'novel_consistency_report';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'report' => 'array',
        'chapter_no' => 'integer',
    ];

    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }
}

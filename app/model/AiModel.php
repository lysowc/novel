<?php

namespace app\model;

use support\Model;

class AiModel extends Model
{
    protected $table = 'ai_model';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
        'max_tokens' => 'integer',
        'temperature' => 'float',
    ];

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}

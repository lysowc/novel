<?php

namespace app\model;

use support\Model;

class AiProvider extends Model
{
    protected $table = 'ai_provider';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function models()
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * 打码后的 api_key
     */
    public function getMaskedKeyAttribute(): string
    {
        $key = (string)$this->api_key;
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 8) {
            return '****';
        }
        return substr($key, 0, 3) . '****' . substr($key, -4);
    }
}

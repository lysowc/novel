<?php

namespace app\model;

use support\Model;

class AiLog extends Model
{
    protected $table = 'ai_log';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    const UPDATED_AT = null;
}

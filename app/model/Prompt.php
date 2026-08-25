<?php

namespace app\model;

use support\Model;

class Prompt extends Model
{
    protected $table = 'prompt';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
}

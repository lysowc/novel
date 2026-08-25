<?php

namespace app\model;

use support\Model;

class Category extends Model
{
    protected $table = 'category';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
    ];
}

<?php

namespace app\model;

use support\Model;

class Admin extends Model
{
    protected $table = 'admin';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];
}

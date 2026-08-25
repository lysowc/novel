<?php

namespace app\model;

use support\Model;

class IdeaChat extends Model
{
    protected $table = 'idea_chat';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    const UPDATED_AT = null;
}

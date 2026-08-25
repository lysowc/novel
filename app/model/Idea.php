<?php

namespace app\model;

use support\Model;

class Idea extends Model
{
    protected $table = 'idea';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function chats()
    {
        return $this->hasMany(IdeaChat::class, 'idea_id');
    }
}

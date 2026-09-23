<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonCompletion extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['completed_at' => 'datetime'];
}

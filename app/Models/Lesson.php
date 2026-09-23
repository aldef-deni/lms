<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['preview' => 'boolean'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}

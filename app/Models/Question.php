<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['options' => 'array'];

    protected $hidden = ['answer'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}

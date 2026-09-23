<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'question_scores' => 'array', 'started_at' => 'datetime', 'submitted_at' => 'datetime', 'passed' => 'boolean'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

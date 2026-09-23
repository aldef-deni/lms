<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['completed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function completions()
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    public function getProgressAttribute()
    {
        $total = $this->course->lessons()->count();

        $completed = $this->completions()->whereIn('lesson_id', $this->course->lessons()->select('lessons.id'))->count();

        return $total ? (int) floor($completed / $total * 100) : 0;
    }
}

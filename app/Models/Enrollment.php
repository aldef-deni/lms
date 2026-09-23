<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Enrollment extends Model { protected $guarded=["id"]; protected $casts=['completed_at'=>'datetime']; public function user(){return $this->belongsTo(User::class);} public function course(){return $this->belongsTo(Course::class);} public function completions(){return $this->hasMany(LessonCompletion::class);} public function certificate(){return $this->hasOne(Certificate::class);} public function getProgressAttribute(){ $total=$this->course->lessons()->count(); return $total ? (int) floor($this->completions()->count()/$total*100) : 0; } }

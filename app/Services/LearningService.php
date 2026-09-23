<?php
namespace App\Services;
use App\Models\{Enrollment,Certificate,Setting};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class LearningService {
 public function refresh(Enrollment $enrollment): void {
 DB::transaction(function() use ($enrollment) {
 $e=Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
 if($e->status==='cancelled') return;
 $course=$e->course;
 $ready=$course->lessons()->exists() && $e->progress===100;
 foreach($course->quizzes()->where('required',true)->get() as $quiz) $ready=$ready && $quiz->attempts()->where('user_id',$e->user_id)->where('passed',true)->exists();
 foreach($course->assignments()->where('required',true)->get() as $assignment) $ready=$ready && $assignment->submissions()->where('user_id',$e->user_id)->where('status','graded')->where('grade','>=',70)->exists();
 if(!$ready) return;
 $e->update(['status'=>'completed','completed_at'=>$e->completed_at ?? now()]);
 if(!$e->certificate()->exists()) {
 $prefix=Setting::valueFor('certificate_prefix','ALDEF-LMS');
 Certificate::create(['enrollment_id'=>$e->id,'number'=>$prefix.'-'.now()->year.'-'.str_pad((string)$e->id,6,'0',STR_PAD_LEFT),'verification_token'=>(string)Str::uuid(),'student_name'=>$e->user->name,'course_title'=>$course->title,'instructor_name'=>$course->instructor->name,'completed_at'=>$e->completed_at,'issued_at'=>now()]);
 }
 });
 }
}

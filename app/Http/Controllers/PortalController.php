<?php
namespace App\Http\Controllers;
use App\Models\{Course,Category,User,Enrollment,Certificate,Announcement,ActivityLog,Submission,Assignment,QuizAttempt};
use Illuminate\Http\Request;
class PortalController extends Controller {
 public function home(){ return view('home',['courses'=>Course::with('instructor','category')->where('status','published')->orderByDesc('featured')->latest()->take(3)->get(),'courseCount'=>Course::where('status','published')->count(),'studentCount'=>User::where('role','student')->count()]); }
 public function catalog(Request $r){$courses=Course::with('instructor','category')->where('status','published')->when($r->q,fn($q)=>$q->where('title','like','%'.$r->q.'%'))->when($r->category,fn($q)=>$q->where('category_id',$r->category))->when($r->level,fn($q)=>$q->where('level',$r->level))->paginate(12)->withQueryString();return view('courses.catalog',compact('courses')+['categories'=>Category::all()]);}
 public function course(Course $course){abort_unless($course->status==='published'||auth()->user()?->isAdmin()||auth()->id()===$course->instructor_id,404);$course->load('sections.lessons','instructor','category');return view('courses.show',compact('course'));}
 public function dashboard(Request $r){$u=$r->user();$courses=Course::query()->when(!$u->isAdmin(),fn($q)=>$q->where('instructor_id',$u->id));$enrollments=Enrollment::with('course.instructor','certificate')->where('user_id',$u->id)->latest()->get();
 if($u->canTeach()) {$ids=(clone $courses)->pluck('id');$stats=['Courses'=>$ids->count(),'Learners'=>Enrollment::whereIn('course_id',$ids)->distinct()->count('user_id'),'Enrollments'=>Enrollment::whereIn('course_id',$ids)->count(),'Certificates'=>Certificate::whereHas('enrollment',fn($q)=>$q->whereIn('course_id',$ids))->count()];}else{$stats=['Enrolled courses'=>$enrollments->count(),'In progress'=>$enrollments->where('status','active')->count(),'Completed'=>$enrollments->where('status','completed')->count(),'Certificates'=>Certificate::whereIn('enrollment_id',$enrollments->pluck('id'))->count()];}
 $announcements=Announcement::whereNull('course_id')->orWhereIn('course_id',$u->canTeach() ? $courses->pluck('id') : $enrollments->pluck('course_id'))->latest()->take(5)->get();
 $managedCourses=$u->canTeach() ? $courses->withCount('enrollments','lessons')->latest()->take(6)->get() : collect();
 $activities=$u->isAdmin() ? ActivityLog::with('user')->latest()->take(6)->get() : collect();
 $deadlines=Assignment::whereIn('course_id',$enrollments->pluck('course_id'))->where('due_at','>=',now())->orderBy('due_at')->take(5)->get();
 $pending=$u->canTeach() ? Submission::whereHas('assignment',fn($q)=>$q->whereIn('course_id',$courses->pluck('id')))->where('status','submitted')->count() : 0;
 return view('dashboard',compact('stats','enrollments','announcements','managedCourses','activities','deadlines','pending'));}
 public function reports(Request $r){abort_unless($r->user()->canTeach(),403);$courses=Course::with('instructor')->withCount(['lessons','enrollments','enrollments as completed_count'=>fn($q)=>$q->where('status','completed')])->when(!$r->user()->isAdmin(),fn($q)=>$q->where('instructor_id',$r->user()->id))->get();$enrollments=Enrollment::with('user','course','certificate')->whereIn('course_id',$courses->pluck('id'))->latest()->paginate(25);return view('admin.reports',compact('courses','enrollments'));}
}

<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function home()
    {
        return view('home', ['courses' => Course::with('instructor', 'category')->withCount('lessons', 'enrollments')->where('status', 'published')->orderByDesc('featured')->latest()->take(3)->get(), 'courseCount' => Course::where('status', 'published')->count(), 'studentCount' => User::where('role', 'student')->count()]);
    }

    public function catalog(Request $r)
    {
        $courses = Course::with('instructor', 'category')->withCount('lessons', 'enrollments')->where('status', 'published')->when($r->q, fn ($q) => $q->where('title', 'like', '%'.$r->q.'%'))->when($r->category, fn ($q) => $q->where('category_id', $r->category))->when($r->level, fn ($q) => $q->where('level', $r->level))->orderByDesc('featured')->latest()->paginate(12)->withQueryString();

        return view('courses.catalog', compact('courses') + ['categories' => Category::all()]);
    }

    public function course(Course $course)
    {
        abort_unless($course->status === 'published' || auth()->user()?->isAdmin() || auth()->id() === $course->instructor_id, 404);
        $course->load('sections.lessons', 'instructor', 'category')->loadCount('lessons', 'enrollments', 'quizzes', 'assignments');
        $enrollment = auth()->check() ? Enrollment::where('course_id', $course->id)->where('user_id', auth()->id())->first() : null;

        return view('courses.show', compact('course', 'enrollment'));
    }

    public function dashboard(Request $r)
    {
        $u = $r->user();
        $dashboardType = $u->role;
        if ($u->isCorporateAdmin()) {
            abort_unless($u->organization_id, 403, 'Akun Corporate Admin belum terhubung ke organisasi.');
            $memberIds = User::where('organization_id', $u->organization_id)->pluck('id');
            $enrollments = Enrollment::with('user', 'course.instructor', 'certificate')->whereIn('user_id', $memberIds)->latest()->get();
            $courseIds = $enrollments->pluck('course_id')->unique();
            $stats = [
                'Organization members' => $memberIds->count(),
                'Active enrollments' => $enrollments->where('status', 'active')->count(),
                'Completed courses' => $enrollments->where('status', 'completed')->count(),
                'Certificates' => Certificate::whereIn('enrollment_id', $enrollments->pluck('id'))->count(),
            ];
            $announcements = Announcement::whereNull('course_id')->orWhereIn('course_id', $courseIds)->latest()->take(5)->get();
            $managedCourses = collect();
            $activities = collect();
            $deadlines = collect();
            $pending = 0;
            $summary = [
                'Organization' => $u->organizationRecord?->name ?? 'Not configured',
                'Students' => User::where('organization_id', $u->organization_id)->where('role', 'student')->count(),
                'Courses accessed' => $courseIds->count(),
            ];
            $growth = collect();

            return view('dashboard', compact('dashboardType', 'stats', 'enrollments', 'announcements', 'managedCourses', 'activities', 'deadlines', 'pending', 'summary', 'growth'));
        }
        $courses = Course::query()->when(! $u->isAdmin(), fn ($q) => $q->where('instructor_id', $u->id));
        $enrollments = Enrollment::with('course.instructor', 'certificate')->where('user_id', $u->id)->latest()->get();
        if ($u->canTeach()) {
            $ids = (clone $courses)->pluck('id');
            $stats = ['Courses' => $ids->count(), 'Learners' => Enrollment::whereIn('course_id', $ids)->distinct()->count('user_id'), 'Enrollments' => Enrollment::whereIn('course_id', $ids)->count(), 'Certificates' => Certificate::whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $ids))->count()];
        } else {
            $stats = ['Enrolled courses' => $enrollments->count(), 'In progress' => $enrollments->where('status', 'active')->count(), 'Completed' => $enrollments->where('status', 'completed')->count(), 'Certificates' => Certificate::whereIn('enrollment_id', $enrollments->pluck('id'))->count()];
        }
        $announcements = Announcement::whereNull('course_id')->orWhereIn('course_id', $u->canTeach() ? $courses->pluck('id') : $enrollments->pluck('course_id'))->latest()->take(5)->get();
        $managedCourses = $u->canTeach() ? (clone $courses)->withCount('enrollments', 'lessons')->latest()->take(6)->get() : collect();
        $activities = $u->isAdmin() ? ActivityLog::with('user')->latest()->take(6)->get() : collect();
        $deadlines = Assignment::whereIn('course_id', $enrollments->pluck('course_id'))->where('due_at', '>=', now())->orderBy('due_at')->take(5)->get();
        $pending = $u->canTeach() ? Submission::whereHas('assignment', fn ($q) => $q->whereIn('course_id', $courses->pluck('id')))->where('status', 'submitted')->count()
            + QuizAttempt::whereHas('quiz', fn ($q) => $q->whereIn('course_id', $courses->pluck('id')))->where('grading_status', 'pending')->count() : 0;

        $summary = [];
        $growth = collect();
        if ($u->canTeach()) {
            $ids = (clone $courses)->pluck('id');
            $summary = [
                'Published courses' => (clone $courses)->where('status', 'published')->count(),
                'Drafts awaiting publication' => (clone $courses)->where('status', 'draft')->count(),
                'Lessons' => Lesson::whereHas('section', fn ($q) => $q->whereIn('course_id', $ids))->count(),
                'Assessments' => Quiz::whereIn('course_id', $ids)->count(),
                'Completed lessons' => LessonCompletion::whereIn('enrollment_id', Enrollment::whereIn('course_id', $ids)->select('id'))->count(),
            ];
            if ($u->isAdmin()) {
                $summary = ['Registered students' => User::where('role', 'student')->count(), 'Instructors' => User::where('role', 'instructor')->count()] + $summary;
            }
            for ($month = 5; $month >= 0; $month--) {
                $start = now()->startOfMonth()->subMonths($month);
                $growth->push(['label' => $start->format('M'), 'count' => Enrollment::whereIn('course_id', $ids)->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])->count()]);
            }
        }

        return view('dashboard', compact('dashboardType', 'stats', 'enrollments', 'announcements', 'managedCourses', 'activities', 'deadlines', 'pending', 'summary', 'growth'));
    }

    public function reports(Request $r)
    {
        $user = $r->user();
        abort_unless($user->canAny(['reports.view', 'reports.view-own', 'reports.view-organization']), 403);
        $organizationId = $user->organization_id;
        $courses = Course::with('instructor')->withCount([
            'lessons',
            'enrollments' => fn ($q) => $q->when($user->isCorporateAdmin(), fn ($q) => $q->whereHas('user', fn ($q) => $q->where('organization_id', $organizationId))),
            'enrollments as completed_count' => fn ($q) => $q->where('status', 'completed')->when($user->isCorporateAdmin(), fn ($q) => $q->whereHas('user', fn ($q) => $q->where('organization_id', $organizationId))),
        ]);
        if ($user->hasRole('Instructor')) {
            $courses->where('instructor_id', $user->id);
        } elseif ($user->isCorporateAdmin()) {
            abort_unless($user->organization_id, 403);
            $courses->whereHas('enrollments.user', fn ($q) => $q->where('organization_id', $user->organization_id));
        }
        $courses = $courses->get();
        $enrollments = Enrollment::with('user', 'course', 'certificate')->whereIn('course_id', $courses->pluck('id'))
            ->when($user->isCorporateAdmin(), fn ($q) => $q->whereHas('user', fn ($q) => $q->where('organization_id', $user->organization_id)))
            ->latest()->paginate(25);

        return view('admin.reports', compact('courses', 'enrollments'));
    }
}

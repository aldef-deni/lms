<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Services\LearningService;
use App\Services\ManagementResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private function definition(string $resource): array
    {
        $d = ManagementResources::all()[$resource] ?? null;
        abort_unless($d, 404);
        abort_if(auth()->user()->is_demo && in_array($resource, ['users', 'organizations'], true), 403, 'Demo accounts cannot manage users or organizations.');
        $permissions = match ($resource) {
            'users' => ['users.manage'],
            'organizations' => ['organizations.manage', 'organizations.manage-own'],
            'categories', 'courses' => ['courses.manage', 'courses.manage-own'],
            'certificate-templates' => ['certificates.manage'],
            'enrollments' => ['enrollments.manage', 'enrollments.manage-organization'],
            'sections', 'lessons' => ['lessons.manage', 'lessons.manage-own'],
            'quizzes', 'questions', 'assignments' => ['assessments.manage', 'assessments.manage-own'],
            'announcements' => ['announcements.manage', 'announcements.manage-own'],
            default => [],
        };
        abort_unless(auth()->user()->canAny($permissions), 403);

        return $d;
    }

    private function scoped(string $resource)
    {
        [$class] = $this->definition($resource);
        $q = $class::query();
        $user = auth()->user();
        if ($user->is_demo) {
            return match ($resource) {
                'categories', 'courses', 'certificate-templates' => $q->where('is_demo', true),
                'enrollments' => $q->whereHas('user', fn ($query) => $query->where('is_demo', true))->whereHas('course', fn ($query) => $query->where('is_demo', true)),
                'sections', 'quizzes', 'assignments' => $q->whereHas('course', fn ($query) => $query->where('is_demo', true)),
                'lessons' => $q->whereHas('section.course', fn ($query) => $query->where('is_demo', true)),
                'questions' => $q->whereHas('quiz.course', fn ($query) => $query->where('is_demo', true)),
                'announcements' => $q->whereHas('user', fn ($query) => $query->where('is_demo', true)),
                default => $q->whereRaw('1=0'),
            };
        }
        $q = match ($resource) {
            'users', 'categories', 'courses', 'certificate-templates' => $q->where('is_demo', false),
            'enrollments' => $q->whereHas('user', fn ($query) => $query->where('is_demo', false))->whereHas('course', fn ($query) => $query->where('is_demo', false)),
            'sections', 'quizzes', 'assignments' => $q->whereHas('course', fn ($query) => $query->where('is_demo', false)),
            'lessons' => $q->whereHas('section.course', fn ($query) => $query->where('is_demo', false)),
            'questions' => $q->whereHas('quiz.course', fn ($query) => $query->where('is_demo', false)),
            'announcements' => $q->whereHas('user', fn ($query) => $query->where('is_demo', false)),
            default => $q,
        };
        if ($user->hasRole('Super Admin')) {
            return $q;
        }
        if ($user->hasRole('Admin LMS')) {
            return $resource === 'users' ? $q->whereIn('role', ['student', 'instructor']) : $q;
        }
        if ($user->isCorporateAdmin()) {
            abort_unless($user->organization_id, 403);

            return match ($resource) {
                'users' => $q->where('organization_id', $user->organization_id)->where('role', 'student'),
                'enrollments' => $q->whereHas('user', fn ($q) => $q->where('organization_id', $user->organization_id)),
                'organizations' => $q->whereKey($user->organization_id),
                default => $q->whereRaw('1=0'),
            };
        }
        $id = $user->id;

        return match ($resource) {
            'courses' => $q->where('instructor_id', $id),'sections','quizzes','assignments','announcements' => $q->whereHas('course', fn ($q) => $q->where('instructor_id', $id)),'lessons' => $q->whereHas('section.course', fn ($q) => $q->where('instructor_id', $id)),'questions' => $q->whereHas('quiz.course', fn ($q) => $q->where('instructor_id', $id)),default => $q->whereRaw('1=0')
        };
    }

    private function courseAccess(int $id): void
    {
        $user = auth()->user();
        if ($user->is_demo) {
            $course = Course::whereKey($id)->where('is_demo', true);
            if (! $user->isAdmin()) {
                $course->where('instructor_id', $user->id);
            }
            abort_unless($course->exists(), 403);

            return;
        }
        $course = Course::whereKey($id)->where('is_demo', false);
        abort_unless((clone $course)->exists() && ($user->isAdmin() || ($user->isCorporateAdmin() && (clone $course)->where('status', 'published')->exists()) || (clone $course)->where('instructor_id', $user->id)->exists()), 403);
    }

    private function options(): array
    {
        $user = auth()->user();
        $courses = Course::where('is_demo', $user->is_demo)->when($user->hasRole('Instructor'), fn ($q) => $q->where('instructor_id', $user->id))
            ->when($user->isCorporateAdmin(), fn ($q) => $q->where('status', 'published'))->get();
        $ids = $courses->pluck('id');

        $user = auth()->user();
        $students = User::where('role', 'student')->where('is_demo', $user->is_demo)->when($user->isCorporateAdmin(), fn ($q) => $q->where('organization_id', $user->organization_id))->pluck('name', 'id');
        $organizations = Organization::when($user->isCorporateAdmin(), fn ($q) => $q->whereKey($user->organization_id))->where('active', true)->pluck('name', 'id');

        return ['courses' => $courses->pluck('title', 'id'), 'categories' => Category::where('is_demo', $user->is_demo)->pluck('name', 'id'), 'instructors' => User::whereIn('role', ['instructor', 'admin', 'super_admin'])->where('active', true)->where('is_demo', $user->is_demo)->pluck('name', 'id'), 'students' => $students, 'organizations' => $organizations, 'sections' => Section::whereIn('course_id', $ids)->get()->mapWithKeys(fn ($s) => [$s->id => $s->course->title.' / '.$s->title]), 'lessons' => Lesson::whereHas('section', fn ($q) => $q->whereIn('course_id', $ids))->pluck('title', 'id'), 'quizzes' => Quiz::whereIn('course_id', $ids)->pluck('title', 'id'), 'certificate-templates' => CertificateTemplate::where('is_demo', $user->is_demo)->pluck('name', 'id')];
    }

    public function index(Request $r, string $resource)
    {
        [$class,$title,$fields] = $this->definition($resource);
        $q = $this->scoped($resource);
        $search = in_array('title', array_keys($fields)) ? 'title' : (isset($fields['name']) ? 'name' : (isset($fields['prompt']) ? 'prompt' : null));
        $r->validate(['q' => 'nullable|string|max:255', 'role' => 'nullable|string', 'status' => 'nullable|string', 'active' => 'nullable|boolean', 'course_id' => 'nullable|integer']);
        if ($r->q && $search) {
            $q->where(function ($query) use ($search, $r, $resource) {
                $query->where($search, 'like', '%'.$r->q.'%');
                if ($resource === 'users') {
                    $query->orWhere('email', 'like', '%'.$r->q.'%');
                }
            });
        }
        foreach (['role', 'status', 'active', 'course_id'] as $filter) {
            if ($r->filled($filter) && isset($fields[$filter])) {
                $q->where($filter, $r->$filter);
            }
        }
        $records = $q->latest()->paginate(15)->withQueryString();
        $options = $this->options();

        return view('admin.index', compact('title', 'resource', 'fields', 'records', 'options'));
    }

    public function form(string $resource, ?int $id = null)
    {
        [$class,$title,$fields] = $this->definition($resource);
        abort_if($resource === 'organizations' && auth()->user()->isCorporateAdmin() && ! $id, 403);
        $record = $id ? $this->scoped($resource)->findOrFail($id) : new $class;
        if (! $record->exists) {
            $record->fill(match ($resource) {
                'users' => ['role' => 'student', 'active' => true],
                'courses' => ['instructor_id' => auth()->id(), 'level' => 'beginner', 'status' => 'draft', 'featured' => false],
                'sections' => ['position' => 0],
                'lessons' => ['type' => 'text', 'position' => 0, 'duration' => 10, 'preview' => false],
                'quizzes' => ['passing_grade' => 70, 'max_attempts' => 3, 'required' => true, 'type' => 'quiz'],
                'questions' => ['points' => 1, 'type' => 'multiple_choice', 'media_type' => 'text'],
                'certificate-templates' => ['heading' => 'Certificate of Completion', 'accent' => '#5653d9', 'signatory' => 'ALDEF Academy', 'signature_mode' => 'upload'],
                default => [],
            });
        }
        $options = $this->options();
        if ($resource === 'users' && ! auth()->user()->hasRole('Super Admin')) {
            $allowedRoles = auth()->user()->isCorporateAdmin() ? ['student'] : ['student', 'instructor'];
            $fields['role'] = array_intersect_key(User::ROLES, array_flip($allowedRoles));
            abort_if($record->exists && ! in_array($record->role, $allowedRoles), 403);
        }

        return view('admin.form', compact('resource', 'title', 'fields', 'record', 'options'));
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        [$class,$title,$fields,$rules] = $this->definition($resource);
        abort_if($resource === 'organizations' && auth()->user()->isCorporateAdmin() && ! $id, 403);
        $record = $id ? $this->scoped($resource)->findOrFail($id) : new $class;
        if ($resource === 'users') {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)];
            $rules['username'] = ['nullable', 'alpha_dash', 'max:80', Rule::unique('users')->ignore($id)];
            $rules['password'] = $id ? 'nullable|string|min:10' : 'required|string|min:10';
        }
        if (in_array($resource, ['categories', 'courses', 'organizations'])) {
            $rules['slug'] = ['required', 'alpha_dash', 'max:255', Rule::unique($record->getTable())->ignore($id)];
        }
        $data = $r->validate($rules);
        if ($r->user()->is_demo && in_array($resource, ['categories', 'courses', 'certificate-templates'], true)) {
            $data['is_demo'] = true;
        }
        if ($resource === 'courses') {
            $demoScope = (bool) $r->user()->is_demo;
            abort_if((bool) User::whereKey($data['instructor_id'])->value('is_demo') !== $demoScope, 422, 'Course and instructor must belong to the same data scope.');
            abort_if(! empty($data['category_id']) && (bool) Category::whereKey($data['category_id'])->value('is_demo') !== $demoScope, 422, 'Course and category must belong to the same data scope.');
            abort_if(! empty($data['certificate_template_id']) && (bool) CertificateTemplate::whereKey($data['certificate_template_id'])->value('is_demo') !== $demoScope, 422, 'Course and certificate template must belong to the same data scope.');
        }
        if ($resource === 'categories' && ! empty($data['parent_id'])) {
            abort_if((bool) Category::whereKey($data['parent_id'])->value('is_demo') !== (bool) $r->user()->is_demo, 422, 'Parent category must belong to the same data scope.');
        }
        // Keep learning history attached to its original curriculum.
        if ($record->exists) {
            $parentField = match ($resource) {
                'sections', 'quizzes', 'assignments' => 'course_id',
                'lessons' => 'section_id',
                'questions' => 'quiz_id',
                default => null,
            };
            if ($parentField) {
                abort_if((int) $record->$parentField !== (int) $data[$parentField], 422, 'Create a new record to move content to a different parent.');
            }
        }
        if ($resource === 'quizzes' && $record->exists) {
            abort_if($record->attempts()->exists(), 422, 'This assessment has attempt history. Create a new assessment to change its rules.');
        }
        if ($resource === 'users') {
            if (! auth()->user()->hasRole('Super Admin')) {
                $allowedRoles = auth()->user()->isCorporateAdmin() ? ['student'] : ['student', 'instructor'];
                abort_if(($record->exists && ! in_array($record->role, $allowedRoles)) || ! in_array($data['role'], $allowedRoles), 403);
            }
            if (auth()->user()->isCorporateAdmin()) {
                $data['organization_id'] = auth()->user()->organization_id;
            }
            if ($record->id === auth()->id()) {
                abort_if(! $data['active'] || $data['role'] !== $record->role, 422, 'You cannot deactivate or change your own role.');
            }
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if ($record->role === 'super_admin' && (! $data['active'] || $data['role'] !== 'super_admin')) {
                abort_if(User::where('role', 'super_admin')->where('active', true)->count() <= 1, 422, 'Keep at least one active super admin.');
            }
            abort_if($data['role'] === 'corporate' && empty($data['organization_id']), 422, 'Corporate Admin must belong to an organization.');
        }
        if (isset($data['course_id'])) {
            $this->courseAccess((int) $data['course_id']);
        }
        if ($resource === 'announcements') {
            $data['user_id'] = auth()->id();
            abort_if(! auth()->user()->isAdmin() && empty($data['course_id']), 403);
        }
        if ($resource === 'courses') {
            if (! auth()->user()->isAdmin()) {
                $data['instructor_id'] = auth()->id();
            }
            abort_unless(User::whereKey($data['instructor_id'])->whereIn('role', ['instructor', 'admin', 'super_admin'])->where('active', true)->exists(), 422);
        }
        if ($resource === 'lessons') {
            $section = Section::findOrFail($data['section_id']);
            $this->courseAccess($section->course_id);
            if ($data['type'] === 'embed' && ! empty($data['url'])) {
                $host = parse_url($data['url'], PHP_URL_HOST);
                abort_unless(in_array($host, ['www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com']), 422, 'Use a YouTube or Vimeo embed URL.');
            }
        }
        if ($resource === 'quizzes' && ! empty($data['lesson_id'])) {
            abort_unless(Lesson::findOrFail($data['lesson_id'])->section->course_id === $data['course_id'] * 1, 422, 'The lesson must belong to this course.');
        }
        if ($resource === 'questions') {
            $quiz = Quiz::findOrFail($data['quiz_id']);
            $this->courseAccess($quiz->course_id);
            abort_if($quiz->attempts()->exists(), 422, 'Questions cannot change after an assessment has started. Create a new assessment instead.');
            if ($data['type'] === 'multiple_choice') {
                $data['options'] = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['options'] ?? '')), fn ($option) => $option !== ''));
                abort_unless(count($data['options']) >= 2 && in_array($data['answer'] ?? null, $data['options'], true), 422, 'Provide at least two options and an answer matching one option exactly.');
            } else {
                $data['options'] = null;
                $data['answer'] = $data['answer'] ?? '';
            }
            if ($data['media_type'] === 'image') {
                $existingMedia = $record->media_type === 'image' ? $record->media_path : null;
                abort_unless($r->hasFile('media_path') || $existingMedia, 422, 'Upload an image for this question.');
                abort_if($r->hasFile('media_path') && ! str_starts_with((string) $r->file('media_path')->getMimeType(), 'image/'), 422, 'Image questions require an image file.');
                $data['media_url'] = null;
            } elseif ($data['media_type'] === 'video') {
                $existingMedia = $record->media_type === 'video' ? $record->media_path : null;
                abort_unless($r->hasFile('media_path') || filled($data['media_url'] ?? null) || $existingMedia, 422, 'Upload a video or provide a YouTube/Vimeo URL.');
                abort_if($r->hasFile('media_path') && ! str_starts_with((string) $r->file('media_path')->getMimeType(), 'video/'), 422, 'Video questions require a video file.');
                if (filled($data['media_url'] ?? null)) {
                    $host = strtolower((string) parse_url($data['media_url'], PHP_URL_HOST));
                    abort_unless(in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com']), 422, 'Video URL must use YouTube or Vimeo.');
                }
            } else {
                $data['media_url'] = null;
            }
        }
        if ($resource === 'enrollments') {
            $student = User::whereKey($data['user_id'])->where('role', 'student');
            if (auth()->user()->isCorporateAdmin()) {
                $student->where('organization_id', auth()->user()->organization_id);
            }
            abort_unless($student->exists(), 422);
            abort_if((bool) User::whereKey($data['user_id'])->value('is_demo') !== (bool) Course::whereKey($data['course_id'])->value('is_demo'), 422, 'Demo users may only enroll in demo courses.');
            abort_if(Enrollment::where('user_id', $data['user_id'])->where('course_id', $data['course_id'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'This learner is already enrolled.');
            if ($record->exists) {
                abort_if($record->user_id != $data['user_id'] || $record->course_id != $data['course_id'] || $record->status === 'completed', 422, 'Completed enrollments and enrollment ownership cannot be changed.');
            }
        }
        if ($resource === 'categories' && ! empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            while ($parent) {
                abort_if($parent->id === $record->id, 422, 'A category cannot contain itself.');
                $parent = $parent->parent;
            }
        }
        foreach (['thumbnail', 'attachment', 'media_path', 'background_image', 'signature_image'] as $field) {
            if (! array_key_exists($field, $rules)) {
                continue;
            }
            if ($r->hasFile($field)) {
                [$directory, $disk] = match ($field) {
                    'thumbnail' => ['thumbnails', 'public'],
                    'attachment' => ['materials', 'local'],
                    'media_path' => ['questions', 'public'],
                    'background_image' => ['certificates/backgrounds', 'public'],
                    'signature_image' => ['certificates/signatures', 'public'],
                };
                $data[$field] = $r->file($field)->store($directory, $disk);
            } else {
                unset($data[$field]);
            }
        }
        if ($resource === 'certificate-templates') {
            $signatureData = $data['signature_data'] ?? null;
            unset($data['signature_data']);
            if ($data['signature_mode'] === 'draw' && $signatureData) {
                abort_unless(preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $signatureData, $matches), 422, 'The drawn signature is invalid.');
                $signature = base64_decode($matches[1], true);
                abort_unless($signature !== false && strlen($signature) <= 750000, 422, 'The drawn signature is too large.');
                $path = 'certificates/signatures/'.Str::uuid().'.png';
                Storage::disk('public')->put($path, $signature);
                $data['signature_image'] = $path;
            }
            abort_if($data['signature_mode'] === 'draw' && empty($data['signature_image']) && ! $record->signature_image, 422, 'Draw a signature before saving.');
            abort_if($data['signature_mode'] === 'upload' && empty($data['signature_image']) && ! $record->signature_image, 422, 'Upload a signature image before saving.');
        }
        DB::transaction(function () use ($record, $data, $resource) {
            $record->fill($data)->save();
            if ($resource === 'users') {
                $record->syncRoles(User::ROLES[$record->role]);
            }
            ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Saved '.$resource, 'subject' => (string) $record->id]);
        });

        return redirect('/manage/'.$resource)->with('success', $title.' saved.');
    }

    public function destroy(string $resource, int $id)
    {
        $record = $this->scoped($resource)->findOrFail($id);
        abort_if($resource === 'organizations' && auth()->user()->isCorporateAdmin(), 403);
        if ($resource === 'quizzes') {
            abort_if($record->attempts()->exists(), 422, 'Assessments with attempt history cannot be deleted.');
        }
        if ($resource === 'questions') {
            abort_if($record->quiz->attempts()->exists(), 422, 'Questions with attempt history cannot be deleted.');
        }
        if ($resource === 'assignments') {
            abort_if($record->submissions()->exists(), 422, 'Assignments with submissions cannot be deleted.');
        }
        if (in_array($resource, ['sections', 'lessons'])) {
            $course = $resource === 'sections' ? $record->course : $record->section->course;
            abort_if($course->enrollments()->exists(), 422, 'Curriculum with enrolled learners cannot be deleted. You may edit its content.');
        }
        abort_unless(auth()->user()->can('users.manage') || ! in_array($resource, ['users', 'enrollments']), 403);
        if ($resource === 'users') {
            abort_if($record->id === auth()->id() || $record->role === 'super_admin' || (! auth()->user()->hasRole('Super Admin') && in_array($record->role, ['admin', 'corporate'])), 403);
            abort_if($record->courses()->exists() || $record->enrollments()->exists(), 422, 'Deactivate users with learning history instead.');
        }
        if ($resource === 'courses') {
            abort_if($record->enrollments()->exists(), 422, 'Courses with enrollments must be unpublished instead.');
        }
        if ($resource === 'enrollments') {
            abort_if($record->certificate()->exists(), 422, 'Certified enrollments cannot be deleted.');
        }
        DB::transaction(function () use ($record, $resource) {
            $record->delete();
            ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Deleted '.$resource, 'subject' => (string) $record->id]);
        });

        return back()->with('success', 'Record deleted.');
    }

    public function submissions()
    {
        abort_unless(auth()->user()->canTeach(), 403);
        $submissions = Submission::with('user', 'assignment.course')->whereHas('assignment.course', fn ($q) => $q->where('is_demo', auth()->user()->is_demo))->when(! auth()->user()->isAdmin(), fn ($q) => $q->whereHas('assignment.course', fn ($q) => $q->where('instructor_id', auth()->id())))->latest()->paginate(20);
        $essayAttempts = QuizAttempt::with('user', 'quiz.course', 'quiz.questions')->where('grading_status', 'pending')->whereHas('quiz.course', fn ($q) => $q->where('is_demo', auth()->user()->is_demo))
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q->whereHas('quiz.course', fn ($q) => $q->where('instructor_id', auth()->id())))
            ->latest()->paginate(10, ['*'], 'essay_page');

        return view('admin.submissions', compact('submissions', 'essayAttempts'));
    }

    public function grade(Request $r, Submission $submission, LearningService $learning)
    {
        abort_unless($r->user()->canTeach(), 403);
        $this->courseAccess($submission->assignment->course_id);
        $data = $r->validate(['grade' => 'required|integer|min:0|max:100', 'feedback' => 'nullable|string|max:5000']);
        $submission->update($data + ['status' => 'graded']);
        $e = Enrollment::where('user_id', $submission->user_id)->where('course_id', $submission->assignment->course_id)->first();
        if ($e) {
            $learning->refresh($e);
        }
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Graded submission', 'subject' => (string) $submission->id]);

        return back()->with('success', 'Grade and feedback saved.');
    }

    public function gradeEssay(Request $r, QuizAttempt $attempt, LearningService $learning)
    {
        abort_unless($r->user()->canTeach(), 403);
        $this->courseAccess($attempt->quiz->course_id);
        abort_unless($attempt->submitted_at && $attempt->grading_status === 'pending', 422, 'This attempt is not awaiting essay grading.');
        $essayQuestions = $attempt->quiz->questions->where('type', 'essay');
        $rules = ['scores' => 'required|array', 'feedback' => 'nullable|string|max:10000'];
        foreach ($essayQuestions as $question) {
            $rules['scores.'.$question->id] = 'required|integer|min:0|max:'.$question->points;
        }
        $data = $r->validate($rules);
        $scores = $attempt->question_scores ?? [];
        foreach ($essayQuestions as $question) {
            $scores[$question->id] = (int) $data['scores'][$question->id];
        }
        $total = $attempt->quiz->questions->sum('points');
        $earned = collect($scores)->sum(fn ($score) => (int) $score);
        $score = $total ? round($earned / $total * 100, 2) : 0;
        $attempt->update(['question_scores' => $scores, 'score' => $score, 'passed' => $score >= $attempt->quiz->passing_grade, 'grading_status' => 'graded', 'feedback' => $data['feedback'] ?? null]);
        $enrollment = Enrollment::where('user_id', $attempt->user_id)->where('course_id', $attempt->quiz->course_id)->first();
        if ($enrollment) {
            $learning->refresh($enrollment);
        }
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Graded essay attempt', 'subject' => (string) $attempt->id]);

        return back()->with('success', 'Essay score and feedback saved.');
    }

    public function settings()
    {
        abort_if(auth()->user()->is_demo, 403, 'Demo accounts cannot change system settings.');
        abort_unless(auth()->user()->can('settings.manage'), 403);

        return view('admin.settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $r)
    {
        abort_if($r->user()->is_demo, 403, 'Demo accounts cannot change system settings.');
        abort_unless($r->user()->can('settings.manage'), 403);
        $data = $r->validate(['app_name' => 'required|string|max:100', 'logo_path' => ['required', 'regex:~^/?assets/[a-zA-Z0-9/_.-]+\.(png|jpg|webp)$~', 'not_regex:~\.\.~'], 'contact_email' => 'required|email', 'certificate_prefix' => 'required|alpha_dash|max:30', 'contact_address' => 'nullable|string|max:1000']);
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Updated settings', 'subject' => 'System']);

        return back()->with('success', 'Settings saved.');
    }

    public function activity()
    {
        abort_unless(auth()->user()->can('audit.view'), 403);

        return view('admin.activity', ['logs' => ActivityLog::with('user')->when(auth()->user()->is_demo, fn ($q) => $q->whereIn('user_id', User::where('is_demo', true)->select('id')))->latest()->paginate(30)]);
    }
}

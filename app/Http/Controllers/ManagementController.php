<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Services\LearningService;
use App\Services\ManagementResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private function definition(string $resource): array
    {
        $d = ManagementResources::all()[$resource] ?? null;
        abort_unless($d, 404);
        abort_unless(auth()->user()->canTeach(), 403);
        if (in_array($resource, ['users', 'categories', 'certificate-templates', 'enrollments'])) {
            abort_unless(auth()->user()->isAdmin(), 403);
        }

        return $d;
    }

    private function scoped(string $resource)
    {
        [$class] = $this->definition($resource);
        $q = $class::query();
        if (auth()->user()->isAdmin()) {
            return $q;
        }
        $id = auth()->id();

        return match ($resource) {
            'courses' => $q->where('instructor_id', $id),'sections','quizzes','assignments','announcements' => $q->whereHas('course', fn ($q) => $q->where('instructor_id', $id)),'lessons' => $q->whereHas('section.course', fn ($q) => $q->where('instructor_id', $id)),'questions' => $q->whereHas('quiz.course', fn ($q) => $q->where('instructor_id', $id)),default => $q->whereRaw('1=0')
        };
    }

    private function courseAccess(int $id): void
    {
        abort_unless(auth()->user()->isAdmin() || Course::whereKey($id)->where('instructor_id', auth()->id())->exists(), 403);
    }

    private function options(): array
    {
        $courses = Course::when(! auth()->user()->isAdmin(), fn ($q) => $q->where('instructor_id', auth()->id()))->get();
        $ids = $courses->pluck('id');

        return ['courses' => $courses->pluck('title', 'id'), 'categories' => Category::pluck('name', 'id'), 'instructors' => User::whereIn('role', ['instructor', 'admin', 'super_admin'])->where('active', true)->pluck('name', 'id'), 'students' => auth()->user()->isAdmin() ? User::whereIn('role', ['student', 'corporate'])->pluck('name', 'id') : collect(), 'sections' => Section::whereIn('course_id', $ids)->get()->mapWithKeys(fn ($s) => [$s->id => $s->course->title.' / '.$s->title]), 'lessons' => Lesson::whereHas('section', fn ($q) => $q->whereIn('course_id', $ids))->pluck('title', 'id'), 'quizzes' => Quiz::whereIn('course_id', $ids)->pluck('title', 'id'), 'certificate-templates' => CertificateTemplate::pluck('name', 'id')];
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
        $record = $id ? $this->scoped($resource)->findOrFail($id) : new $class;
        if (! $record->exists) {
            $record->fill(match ($resource) {
                'users' => ['role' => 'student', 'active' => true],
                'courses' => ['instructor_id' => auth()->id(), 'level' => 'beginner', 'status' => 'draft', 'featured' => false],
                'sections' => ['position' => 0],
                'lessons' => ['type' => 'text', 'position' => 0, 'duration' => 10, 'preview' => false],
                'quizzes' => ['passing_grade' => 70, 'max_attempts' => 3, 'required' => true, 'type' => 'quiz'],
                'questions' => ['points' => 1, 'type' => 'multiple_choice'],
                'certificate-templates' => ['heading' => 'Certificate of Completion', 'accent' => '#5653d9', 'signatory' => 'ALDEF Academy'],
                default => [],
            });
        }
        $options = $this->options();
        if ($resource === 'users' && auth()->user()->role !== 'super_admin') {
            $fields['role'] = array_intersect_key(User::ROLES, array_flip(['student', 'instructor', 'corporate']));
            abort_if($record->exists && in_array($record->role, ['super_admin', 'admin']), 403);
        }

        return view('admin.form', compact('resource', 'title', 'fields', 'record', 'options'));
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        [$class,$title,$fields,$rules] = $this->definition($resource);
        $record = $id ? $this->scoped($resource)->findOrFail($id) : new $class;
        if ($resource === 'users') {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)];
            $rules['password'] = $id ? 'nullable|string|min:10' : 'required|string|min:10';
        }
        if (in_array($resource, ['categories', 'courses'])) {
            $rules['slug'] = ['required', 'alpha_dash', 'max:255', Rule::unique($record->getTable())->ignore($id)];
        }
        $data = $r->validate($rules);
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
            if (auth()->user()->role !== 'super_admin') {
                abort_if(in_array($record->role, ['admin', 'super_admin']) || in_array($data['role'], ['admin', 'super_admin']), 403);
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
            $data['options'] = $data['type'] === 'true_false' ? ['True', 'False'] : array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['options'] ?? '')), fn ($option) => $option !== ''));
            if ($data['type'] !== 'short_answer') {
                abort_unless(count($data['options']) >= 2 && in_array($data['answer'], $data['options'], true), 422, 'Provide at least two options and an answer matching one option exactly.');
            }
        }
        if ($resource === 'enrollments') {
            abort_unless(User::whereKey($data['user_id'])->whereIn('role', ['student', 'corporate'])->exists(), 422);
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
        foreach (['thumbnail', 'attachment'] as $field) {
            if (! array_key_exists($field, $rules)) {
                continue;
            }
            if ($r->hasFile($field)) {
                $data[$field] = $r->file($field)->store($field === 'thumbnail' ? 'thumbnails' : 'materials', $field === 'thumbnail' ? 'public' : 'local');
            } else {
                unset($data[$field]);
            }
        }
        DB::transaction(function () use ($record, $data, $resource) {
            $record->fill($data)->save();
            ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Saved '.$resource, 'subject' => (string) $record->id]);
        });

        return redirect('/manage/'.$resource)->with('success', $title.' saved.');
    }

    public function destroy(string $resource, int $id)
    {
        $record = $this->scoped($resource)->findOrFail($id);
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
        abort_unless(auth()->user()->isAdmin() || ! in_array($resource, ['users', 'enrollments']), 403);
        if ($resource === 'users') {
            abort_if($record->id === auth()->id() || $record->role === 'super_admin' || (auth()->user()->role !== 'super_admin' && $record->role === 'admin'), 403);
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
        $submissions = Submission::with('user', 'assignment.course')->when(! auth()->user()->isAdmin(), fn ($q) => $q->whereHas('assignment.course', fn ($q) => $q->where('instructor_id', auth()->id())))->latest()->paginate(20);

        return view('admin.submissions', compact('submissions'));
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

    public function settings()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('admin.settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $r)
    {
        abort_unless($r->user()->role === 'super_admin', 403);
        $data = $r->validate(['app_name' => 'required|string|max:100', 'logo_path' => ['required', 'regex:~^/?assets/[a-zA-Z0-9/_.-]+\.(png|jpg|webp)$~', 'not_regex:~\.\.~'], 'contact_email' => 'required|email', 'certificate_prefix' => 'required|alpha_dash|max:30', 'contact_address' => 'nullable|string|max:1000']);
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Updated settings', 'subject' => 'System']);

        return back()->with('success', 'Settings saved.');
    }

    public function activity()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('admin.activity', ['logs' => ActivityLog::with('user')->latest()->paginate(30)]);
    }
}

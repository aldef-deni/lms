<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Services\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LearningController extends Controller
{
    private function enrollment(Course $course): Enrollment
    {
        abort_if((bool) $course->is_demo !== (bool) auth()->user()->is_demo, 404);

        return Enrollment::where('course_id', $course->id)->where('user_id', auth()->id())->whereIn('status', ['active', 'completed'])->firstOrFail();
    }

    public function enroll(Course $course)
    {
        abort_unless($course->status === 'published', 404);
        abort_unless(auth()->user()->hasRole('Student'), 403);
        abort_if((bool) $course->is_demo !== (bool) auth()->user()->is_demo, 404);
        $e = Enrollment::firstOrCreate(['user_id' => auth()->id(), 'course_id' => $course->id]);
        abort_if($e->status === 'cancelled', 403, 'Contact your administrator to reactivate enrollment.');

        return redirect('/learn/'.$course->id);
    }

    public function show(Course $course, ?Lesson $lesson = null)
    {
        $enrollment = $this->enrollment($course);
        $course->load('sections.lessons', 'quizzes', 'assignments');
        if ($lesson) {
            abort_unless($lesson->section->course_id === $course->id, 404);
        } else {
            $lesson = Lesson::find($enrollment->last_lesson_id) ?? $course->sections->flatMap->lessons->first();
        }
        if ($lesson) {
            $enrollment->update(['last_lesson_id' => $lesson->id]);
        }
        $completed = $enrollment->completions()->pluck('lesson_id')->all();
        $discussions = Discussion::with('user', 'replies')->where('course_id', $course->id)->whereNull('parent_id')->latest()->paginate(15);
        $submissions = Submission::where('user_id', auth()->id())->whereIn('assignment_id', $course->assignments->pluck('id'))->get()->keyBy('assignment_id');

        return view('learning.show', compact('course', 'lesson', 'enrollment', 'completed', 'discussions', 'submissions'));
    }

    public function preview(Lesson $lesson)
    {
        abort_unless($lesson->preview && $lesson->section->course->status === 'published', 404);

        return view('learning.preview', compact('lesson'));
    }

    public function complete(Lesson $lesson, LearningService $service)
    {
        $enrollment = $this->enrollment($lesson->section->course);
        LessonCompletion::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], ['completed_at' => now()]);
        $service->refresh($enrollment);

        return back()->with('success', 'Lesson completed. Your progress has been saved.');
    }

    public function material(Lesson $lesson)
    {
        $course = $lesson->section->course;
        abort_if(auth()->check() && (bool) $course->is_demo !== (bool) auth()->user()->is_demo, 404);
        if (! ($lesson->preview && $course->status === 'published') && ! auth()->user()?->isAdmin() && auth()->id() !== $course->instructor_id) {
            $this->enrollment($course);
        }
        abort_unless($lesson->attachment && Storage::disk('local')->exists($lesson->attachment), 404);

        return Storage::disk('local')->download($lesson->attachment);
    }

    public function quiz(Quiz $quiz)
    {
        $this->enrollment($quiz->course);
        $attempts = $quiz->attempts()->where('user_id', auth()->id())->latest()->get();
        $attempt = $attempts->first(fn ($a) => ! $a->submitted_at);

        return view('learning.quiz', compact('quiz', 'attempts', 'attempt'));
    }

    public function start(Quiz $quiz)
    {
        $e = $this->enrollment($quiz->course);
        abort_unless($quiz->questions()->exists(), 422, 'This assessment is not ready yet.');
        DB::transaction(function () use ($quiz, $e) {
            Enrollment::whereKey($e->id)->lockForUpdate()->first();
            $attempts = $quiz->attempts()->where('user_id', auth()->id());
            if ((clone $attempts)->whereNull('submitted_at')->exists()) {
                return;
            }
            abort_if($attempts->count() >= $quiz->max_attempts, 422, 'No attempts remaining.');
            $quiz->attempts()->create(['user_id' => auth()->id(), 'started_at' => now()]);
        });

        return back();
    }

    public function submitQuiz(Request $r, QuizAttempt $attempt, LearningService $service)
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
        $e = $this->enrollment($attempt->quiz->course);
        $data = $r->validate(['answers' => 'nullable|array', 'answers.*' => 'nullable|string|max:10000']);
        DB::transaction(function () use ($attempt, $data) {
            $attempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_if($attempt->submitted_at, 422, 'Already submitted.');
            $quiz = $attempt->quiz;
            $expired = $quiz->time_limit && now()->greaterThan($attempt->started_at->copy()->addMinutes($quiz->time_limit));
            $earned = 0;
            $total = 0;
            $answers = [];
            $questionScores = [];
            $needsGrading = false;
            foreach ($quiz->questions as $question) {
                $total += $question->points;
                $answer = (string) ($data['answers'][$question->id] ?? '');
                $answers[$question->id] = $answer;
                if ($expired) {
                    $questionScores[$question->id] = 0;
                } elseif ($question->type === 'essay') {
                    $questionScores[$question->id] = null;
                    $needsGrading = true;
                } elseif (mb_strtolower(trim($answer)) === mb_strtolower(trim($question->answer))) {
                    $earned += $question->points;
                    $questionScores[$question->id] = $question->points;
                } else {
                    $questionScores[$question->id] = 0;
                }
            }
            $score = $needsGrading ? null : ($total ? round($earned / $total * 100, 2) : 0);
            $attempt->update(['answers' => $answers, 'question_scores' => $questionScores, 'score' => $score, 'passed' => ! $needsGrading && ! $expired && $total > 0 && $score >= $quiz->passing_grade, 'grading_status' => $needsGrading ? 'pending' : 'graded', 'submitted_at' => now()]);
        });
        $service->refresh($e);

        return redirect('/quizzes/'.$attempt->quiz_id)->with('success', 'Assessment submitted. Your results are below.');
    }

    public function submitAssignment(Request $r, Assignment $assignment)
    {
        $this->enrollment($assignment->course);
        abort_if($assignment->due_at && now()->greaterThan($assignment->due_at), 422, 'The submission deadline has passed.');
        $existing = $assignment->submissions()->where('user_id', auth()->id())->first();
        abort_if($existing?->status === 'graded', 422, 'This submission has already been graded.');
        $data = $r->validate(['notes' => 'nullable|string|max:10000', 'file' => 'nullable|file|mimes:pdf,txt,doc,docx,ppt,pptx,zip,jpg,jpeg,png|max:20480']);
        abort_unless($r->hasFile('file') || filled($data['notes'] ?? null), 422, 'Add notes or upload a file.');
        if ($r->hasFile('file')) {
            $data['file'] = $r->file('file')->store('submissions', 'local');
        }
        $assignment->submissions()->updateOrCreate(['user_id' => auth()->id()], $data + ['status' => 'submitted']);

        return back()->with('success', 'Assignment submitted.');
    }

    public function submissionFile(Submission $submission)
    {
        abort_if((bool) $submission->assignment->course->is_demo !== (bool) auth()->user()->is_demo, 404);
        abort_unless(auth()->id() === $submission->user_id || auth()->user()->isAdmin() || auth()->id() === $submission->assignment->course->instructor_id, 403);
        abort_unless($submission->file && Storage::disk('local')->exists($submission->file), 404);

        return Storage::disk('local')->download($submission->file);
    }

    public function discuss(Request $r, Course $course)
    {
        abort_if((bool) $course->is_demo !== (bool) $r->user()->is_demo, 404);
        if (! $r->user()->isAdmin() && $r->user()->id !== $course->instructor_id) {
            $this->enrollment($course);
        }
        $data = $r->validate(['body' => 'required|string|max:5000', 'parent_id' => 'nullable|integer']);
        if (! empty($data['parent_id'])) {
            abort_unless(Discussion::whereKey($data['parent_id'])->where('course_id', $course->id)->whereNull('parent_id')->exists(), 422);
        }Discussion::create($data + ['course_id' => $course->id, 'user_id' => auth()->id()]);

        return back()->with('success', 'Message posted.');
    }

    public function discussions(Course $course)
    {
        abort_if((bool) $course->is_demo !== (bool) auth()->user()->is_demo, 404);
        abort_unless(auth()->user()->isAdmin() || auth()->id() === $course->instructor_id, 403);
        $discussions = Discussion::with('user', 'replies')->where('course_id', $course->id)->whereNull('parent_id')->latest()->paginate(20);

        return view('learning.discussions', compact('course', 'discussions'));
    }
}

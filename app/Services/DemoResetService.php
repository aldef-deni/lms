<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoResetService
{
    public function reset(): array
    {
        $demoUserIds = User::where('is_demo', true)->pluck('id');
        $demoCourseIds = Course::where('is_demo', true)->orWhereIn('instructor_id', $demoUserIds)->pluck('id');
        $demoEnrollmentIds = Enrollment::whereIn('user_id', $demoUserIds)->orWhereIn('course_id', $demoCourseIds)->pluck('id');

        $publicFiles = User::whereIn('id', $demoUserIds)->whereNotNull('avatar')->pluck('avatar')
            ->merge(Course::whereIn('id', $demoCourseIds)->whereNotNull('thumbnail')->pluck('thumbnail'))
            ->merge(Question::whereHas('quiz', fn ($query) => $query->whereIn('course_id', $demoCourseIds))->whereNotNull('media_path')->pluck('media_path'))
            ->merge(CertificateTemplate::where('is_demo', true)->whereNotNull('background_image')->pluck('background_image'))
            ->merge(CertificateTemplate::where('is_demo', true)->whereNotNull('signature_image')->pluck('signature_image'))
            ->filter()->unique()->values();
        $localFiles = Lesson::whereHas('section', fn ($query) => $query->whereIn('course_id', $demoCourseIds))->whereNotNull('attachment')->pluck('attachment')
            ->merge(Submission::whereIn('user_id', $demoUserIds)->orWhereHas('assignment', fn ($query) => $query->whereIn('course_id', $demoCourseIds))->whereNotNull('file')->pluck('file'))
            ->filter()->unique()->values();

        $deleted = DB::transaction(function () use ($demoUserIds, $demoCourseIds, $demoEnrollmentIds): array {
            $counts = [];
            $counts['certificates'] = Certificate::whereIn('enrollment_id', $demoEnrollmentIds)->delete();
            $counts['quiz_attempts'] = QuizAttempt::whereIn('user_id', $demoUserIds)
                ->orWhereHas('quiz', fn ($query) => $query->whereIn('course_id', $demoCourseIds))->delete();
            $counts['submissions'] = Submission::whereIn('user_id', $demoUserIds)
                ->orWhereHas('assignment', fn ($query) => $query->whereIn('course_id', $demoCourseIds))->delete();
            $counts['discussions'] = Discussion::whereIn('user_id', $demoUserIds)
                ->orWhereIn('course_id', $demoCourseIds)->delete();
            $counts['announcements'] = Announcement::whereIn('user_id', $demoUserIds)
                ->orWhereIn('course_id', $demoCourseIds)->delete();
            $counts['enrollments'] = Enrollment::whereIn('id', $demoEnrollmentIds)->delete();
            $counts['courses'] = Course::whereIn('id', $demoCourseIds)->delete();
            $counts['categories'] = Category::where('is_demo', true)
                ->whereDoesntHave('courses')->delete();
            $counts['certificate_templates'] = CertificateTemplate::where('is_demo', true)
                ->whereNotIn('id', Course::where('is_demo', false)->whereNotNull('certificate_template_id')->select('certificate_template_id'))->delete();
            $counts['activity_logs'] = ActivityLog::whereIn('user_id', $demoUserIds)->delete();
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $demoUserIds)->delete();

            return $counts;
        });

        Storage::disk('public')->delete($publicFiles->all());
        Storage::disk('local')->delete($localFiles->all());

        return $deleted;
    }
}

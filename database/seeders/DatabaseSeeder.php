<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Services\LearningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $permissions = [
                'dashboard.super-admin', 'dashboard.admin', 'dashboard.instructor', 'dashboard.student', 'dashboard.corporate',
                'users.view', 'users.manage', 'roles.manage', 'permissions.manage',
                'courses.view', 'courses.manage', 'courses.manage-own',
                'lessons.manage', 'lessons.manage-own', 'enrollments.manage', 'enrollments.manage-organization',
                'assessments.manage', 'assessments.manage-own', 'assessments.take',
                'assignments.submit', 'grading.manage-own', 'progress.view-own', 'progress.view-course', 'progress.view-organization',
                'certificates.view-own', 'certificates.manage', 'certificates.view-organization',
                'organizations.manage', 'organizations.manage-own', 'reports.view', 'reports.view-own', 'reports.view-organization',
                'announcements.manage', 'announcements.manage-own', 'discussions.participate', 'discussions.moderate-own',
                'settings.manage', 'audit.view', 'profile.manage', 'learning.access',
            ];
            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            $rolePermissions = [
                'Super Admin' => $permissions,
                'Admin LMS' => ['dashboard.admin', 'users.view', 'users.manage', 'courses.view', 'courses.manage', 'lessons.manage', 'enrollments.manage', 'assessments.manage', 'certificates.manage', 'reports.view', 'announcements.manage', 'grading.manage-own', 'progress.view-course', 'discussions.participate', 'discussions.moderate-own', 'profile.manage', 'learning.access'],
                'Instructor' => ['dashboard.instructor', 'courses.view', 'courses.manage-own', 'lessons.manage-own', 'assessments.manage-own', 'grading.manage-own', 'progress.view-course', 'reports.view-own', 'announcements.manage-own', 'discussions.participate', 'discussions.moderate-own', 'profile.manage', 'learning.access'],
                'Student' => ['dashboard.student', 'courses.view', 'assessments.take', 'assignments.submit', 'progress.view-own', 'certificates.view-own', 'discussions.participate', 'profile.manage', 'learning.access'],
                'Corporate Admin' => ['dashboard.corporate', 'users.view', 'users.manage', 'courses.view', 'enrollments.manage-organization', 'progress.view-organization', 'certificates.view-organization', 'organizations.manage-own', 'reports.view-organization', 'discussions.participate', 'profile.manage', 'learning.access'],
            ];
            foreach ($rolePermissions as $name => $grants) {
                Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($grants);
            }

            foreach (['app_name' => 'ALDEF LMS', 'logo_path' => 'assets/logo/aldef-landscape02.png', 'contact_email' => 'hello@aldeftech.com', 'certificate_prefix' => 'ALDEF-LMS', 'contact_address' => ''] as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['value' => $value]);
            }
            $adminPassword = config('lms.superadmin_password');
            if (! $adminPassword) {
                throw new \RuntimeException('LMS_SUPERADMIN_PASSWORD must be set in .env.');
            }
            if (strlen($adminPassword) < 12) {
                throw new \RuntimeException('LMS_SUPERADMIN_PASSWORD must contain at least 12 characters.');
            }
            $admin = User::where('username', config('lms.superadmin_username'))->orWhere('email', config('lms.superadmin_email'))->first() ?? new User;
            $admin->fill(['name' => 'ALDEF Super Admin', 'username' => config('lms.superadmin_username'), 'email' => config('lms.superadmin_email'), 'password' => $adminPassword, 'role' => 'super_admin', 'active' => true, 'email_verified_at' => now()])->save();
            $admin->syncRoles('Super Admin');

            foreach (User::where('id', '!=', $admin->id)->get() as $user) {
                $user->syncRoles(User::ROLES[$user->role] ?? 'Student');
            }
            $categories = [];
            foreach (['Technology & Development', 'Design & Creativity', 'Business & Leadership'] as $name) {
                $categories[] = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => 'Practical skills for your next professional milestone.']);
            }
            $template = CertificateTemplate::firstOrCreate(['name' => 'ALDEF Signature'], ['heading' => 'Certificate of Completion', 'message' => 'For successfully completing the learning journey and required assessments in', 'accent' => '#5653d9', 'signatory' => 'ALDEF Academy']);
            $demo = config('lms.demo_password');
            if (! $demo) {
                $this->command->info('Set LMS_DEMO_PASSWORD to seed optional demo accounts and learning data.');

                return;
            }
            if (strlen($demo) < 12) {
                throw new \RuntimeException('LMS_DEMO_PASSWORD must contain at least 12 characters.');
            }
            $admin = User::firstOrCreate(['email' => 'lms.admin@example.com'], ['name' => 'Nadia Putri', 'role' => 'admin', 'password' => $demo, 'active' => true]);
            $instructor = User::firstOrCreate(['email' => 'mentor@example.com'], ['name' => 'Arif Pratama', 'role' => 'instructor', 'password' => $demo, 'active' => true, 'bio' => 'Technology mentor helping learners turn ideas into practical, useful products.']);
            $student = User::firstOrCreate(['email' => 'student@example.com'], ['name' => 'Alya Rahman', 'role' => 'student', 'password' => $demo, 'active' => true]);
            $admin->syncRoles('Admin LMS');
            $instructor->syncRoles('Instructor');
            $student->syncRoles('Student');
            $titles = ['Web Development Foundations', 'Designing Better Digital Experiences', 'Leadership for Growing Teams'];
            $descriptions = ['Build a clear understanding of how websites work, from semantic HTML and responsive layouts to server-side applications. Learn to turn a simple idea into a thoughtful web experience.', 'Learn to connect user needs with clear interfaces. Explore research, visual hierarchy, and practical approaches to creating accessible digital experiences.', 'Develop the habits that help teams do meaningful work. Explore clear communication, useful feedback, and a practical approach to setting shared goals.'];
            foreach ($titles as $index => $title) {
                $course = Course::firstOrCreate(['slug' => Str::slug($title)], ['title' => $title, 'category_id' => $categories[$index]->id, 'instructor_id' => $instructor->id, 'certificate_template_id' => $template->id, 'description' => $descriptions[$index], 'objectives' => "Understand the core principles.\nApply your knowledge to a practical activity.\nReflect on your progress and identify next steps.", 'requirements' => 'Bring curiosity, a notebook, and a willingness to practice.', 'audience' => 'Curious professionals and learners starting a new chapter.', 'level' => $index === 2 ? 'intermediate' : 'beginner', 'status' => 'published', 'featured' => true, 'tags' => $index === 0 ? 'web, development, foundations' : ($index === 1 ? 'design, research, accessibility' : 'leadership, communication, teamwork')]);
                $lessons = collect();
                foreach (['Getting started', 'From understanding to practice'] as $s => $sectionTitle) {
                    $section = Section::firstOrCreate(['course_id' => $course->id, 'position' => $s], ['title' => $sectionTitle]);
                    foreach (['The essential ideas', 'A practical next step'] as $l => $lessonTitle) {
                        $lesson = Lesson::firstOrCreate(['section_id' => $section->id, 'position' => $l], ['title' => $lessonTitle, 'type' => 'text', 'duration' => 8 + $l * 4, 'preview' => $s === 0 && $l === 0, 'content' => "Welcome to {$title}.\n\n".($index === 0 ? "A useful website begins with a clear purpose. HTML describes the content, CSS controls its presentation, and server-side code handles the data and workflows. Keep these responsibilities clear as you plan your first project.\n\nPractice: sketch a simple learning page with a heading, a short article, and a navigation link. Identify which parts are content and which are presentation." : ($index === 1 ? "Good design starts with understanding a person and the task they want to complete. Clear labels, readable type, and a consistent hierarchy reduce the effort needed to use an interface.\n\nPractice: choose a familiar screen and write down its main task. Sketch a simpler version that makes that task easier to complete." : "Strong teams benefit from clear expectations and useful feedback. A shared goal should explain what success looks like, who is responsible, and when the team will review progress.\n\nPractice: describe one team goal in a single sentence, then list the next three actions and who will own each one."))."\n\nReflection\nWhat did you learn? What will you try next? Write a short note before continuing."]);
                        $lessons->push($lesson);
                    }
                }
                $quiz = Quiz::firstOrCreate(['course_id' => $course->id, 'title' => 'Foundations checkpoint'], ['passing_grade' => 70, 'time_limit' => 15, 'max_attempts' => 3, 'required' => true]);
                $prompt = ['Which technology describes the content of a web page?', 'What should guide a useful interface design?', 'What helps a team work toward a shared goal?'][$index];
                $options = [['HTML', 'CSS', 'A database'], ['User needs', 'Decoration alone', 'The longest possible form'], ['Clear expectations', 'Unclear ownership', 'Avoiding feedback']][$index];
                Question::firstOrCreate(['quiz_id' => $quiz->id, 'prompt' => $prompt], ['type' => 'multiple_choice', 'options' => $options, 'answer' => $options[0], 'points' => 1]);
                Question::firstOrCreate(['quiz_id' => $quiz->id, 'prompt' => 'Reflection and practice help turn learning into usable skills.'], ['type' => 'true_false', 'options' => ['True', 'False'], 'answer' => 'True', 'points' => 1]);
                $assignment = Assignment::firstOrCreate(['course_id' => $course->id, 'title' => 'Your practical learning reflection'], ['instructions' => 'Describe one idea you learned, share a small practical example, and explain what you would improve next. Submit notes or a document.', 'required' => false]);
                $enrollment = Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id]);
                if ($index === 0) {
                    foreach ($lessons as $lesson) {
                        LessonCompletion::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], ['completed_at' => now()]);
                    }
                    $answers = $quiz->questions->mapWithKeys(fn ($q) => [$q->id => $q->answer])->all();
                    QuizAttempt::firstOrCreate(['quiz_id' => $quiz->id, 'user_id' => $student->id], ['answers' => $answers, 'score' => 100, 'passed' => true, 'started_at' => now()->subMinutes(5), 'submitted_at' => now()]);
                    app(LearningService::class)->refresh($enrollment);
                } elseif ($index === 1) {
                    LessonCompletion::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lessons->first()->id], ['completed_at' => now()]);
                }
            }
            Announcement::firstOrCreate(['title' => 'Welcome to your next chapter'], ['user_id' => $admin->id, 'body' => 'Your academy is ready. Explore a course, take your first lesson, and make room for a little progress every day.']);
        });
    }
}

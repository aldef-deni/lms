<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\User;

class ManagementResources
{
    public static function all(): array
    {
        return [
            'users' => [User::class, 'Users', ['name' => 'text', 'username' => 'text', 'email' => 'email', 'password' => 'password', 'role' => User::ROLES, 'organization_id' => 'organizations', 'active' => ['1' => 'Active', '0' => 'Inactive'], 'bio' => 'textarea'], ['name' => 'required|string|max:120', 'username' => 'nullable|alpha_dash|max:80', 'email' => 'required|email|max:255', 'password' => 'nullable|string|min:10', 'role' => 'required|in:super_admin,admin,instructor,student,corporate', 'organization_id' => 'nullable|exists:organizations,id', 'active' => 'required|boolean', 'bio' => 'nullable|string|max:2000']],
            'organizations' => [Organization::class, 'Organizations', ['name' => 'text', 'slug' => 'text', 'email' => 'email', 'phone' => 'text', 'address' => 'textarea', 'active' => ['1' => 'Active', '0' => 'Inactive']], ['name' => 'required|string|max:255', 'slug' => 'required|alpha_dash|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:2000', 'active' => 'required|boolean']],
            'categories' => [Category::class, 'Categories', ['name' => 'text', 'slug' => 'text', 'parent_id' => 'categories', 'description' => 'textarea'], ['name' => 'required|string|max:255', 'slug' => 'required|alpha_dash|max:255', 'parent_id' => 'nullable|exists:categories,id', 'description' => 'nullable|string|max:5000']],
            'courses' => [Course::class, 'Courses', ['title' => 'text', 'slug' => 'text', 'category_id' => 'categories', 'instructor_id' => 'instructors', 'certificate_template_id' => 'certificate-templates', 'description' => 'textarea', 'objectives' => 'textarea', 'requirements' => 'textarea', 'audience' => 'textarea', 'level' => ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'], 'tags' => 'text', 'thumbnail' => 'image', 'status' => ['draft' => 'Draft', 'published' => 'Published'], 'featured' => ['0' => 'Standard', '1' => 'Featured']], ['title' => 'required|string|max:255', 'slug' => 'required|alpha_dash|max:255', 'category_id' => 'nullable|exists:categories,id', 'instructor_id' => 'required|exists:users,id', 'certificate_template_id' => 'nullable|exists:certificate_templates,id', 'description' => 'required|string|max:30000', 'objectives' => 'nullable|string|max:10000', 'requirements' => 'nullable|string|max:10000', 'audience' => 'nullable|string|max:10000', 'level' => 'required|in:beginner,intermediate,advanced', 'tags' => 'nullable|string|max:255', 'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'status' => 'required|in:draft,published', 'featured' => 'required|boolean']],
            'sections' => [Section::class, 'Chapters', ['course_id' => 'courses', 'title' => 'text', 'position' => 'number'], ['course_id' => 'required|exists:courses,id', 'title' => 'required|string|max:255', 'position' => 'required|integer|min:0|max:10000']],
            'lessons' => [Lesson::class, 'Lessons', ['section_id' => 'sections', 'title' => 'text', 'type' => ['text' => 'Article', 'video' => 'Video', 'embed' => 'Embedded video', 'document' => 'PDF / document', 'download' => 'Download', 'external' => 'External link', 'live' => 'Live session'], 'content' => 'textarea', 'url' => 'url', 'attachment' => 'file', 'duration' => 'number', 'position' => 'number', 'preview' => ['0' => 'Enrolled learners', '1' => 'Public preview']], ['section_id' => 'required|exists:sections,id', 'title' => 'required|string|max:255', 'type' => 'required|in:text,video,embed,document,download,external,live', 'content' => 'nullable|string|max:100000', 'url' => 'nullable|url:http,https|max:2000', 'attachment' => 'nullable|file|mimes:pdf,txt,doc,docx,ppt,pptx,xls,xlsx,zip,mp4,webm|max:51200', 'duration' => 'required|integer|min:0|max:10000', 'position' => 'required|integer|min:0|max:10000', 'preview' => 'required|boolean']],
            'enrollments' => [Enrollment::class, 'Enrollments', ['user_id' => 'students', 'course_id' => 'courses', 'status' => ['active' => 'Active', 'cancelled' => 'Cancelled']], ['user_id' => 'required|exists:users,id', 'course_id' => 'required|exists:courses,id', 'status' => 'required|in:active,cancelled']],
            'quizzes' => [Quiz::class, 'Quizzes & exams', ['course_id' => 'courses', 'lesson_id' => 'lessons', 'title' => 'text', 'type' => ['quiz' => 'Quiz', 'exam' => 'Exam'], 'passing_grade' => 'number', 'time_limit' => 'number', 'max_attempts' => 'number', 'required' => ['1' => 'Required for certificate', '0' => 'Optional']], ['course_id' => 'required|exists:courses,id', 'lesson_id' => 'nullable|exists:lessons,id', 'title' => 'required|string|max:255', 'type' => 'required|in:quiz,exam', 'passing_grade' => 'required|integer|min:1|max:100', 'time_limit' => 'nullable|integer|min:1|max:600', 'max_attempts' => 'required|integer|min:1|max:100', 'required' => 'required|boolean']],
            'questions' => [Question::class, 'Question bank', ['quiz_id' => 'quizzes', 'prompt' => 'textarea', 'type' => ['multiple_choice' => 'Multiple choice', 'true_false' => 'True / false', 'short_answer' => 'Short answer (exact match)'], 'options' => 'textarea', 'answer' => 'text', 'points' => 'number'], ['quiz_id' => 'required|exists:quizzes,id', 'prompt' => 'required|string|max:10000', 'type' => 'required|in:multiple_choice,true_false,short_answer', 'options' => 'nullable|string|max:10000', 'answer' => 'required|string|max:2000', 'points' => 'required|integer|min:1|max:100']],
            'assignments' => [Assignment::class, 'Assignments', ['course_id' => 'courses', 'title' => 'text', 'instructions' => 'textarea', 'due_at' => 'datetime-local', 'required' => ['0' => 'Optional', '1' => 'Required for certificate']], ['course_id' => 'required|exists:courses,id', 'title' => 'required|string|max:255', 'instructions' => 'required|string|max:30000', 'due_at' => 'nullable|date', 'required' => 'required|boolean']],
            'announcements' => [Announcement::class, 'Announcements', ['course_id' => 'courses', 'title' => 'text', 'body' => 'textarea'], ['course_id' => 'nullable|exists:courses,id', 'title' => 'required|string|max:255', 'body' => 'required|string|max:10000']],
            'certificate-templates' => [CertificateTemplate::class, 'Certificate templates', ['name' => 'text', 'heading' => 'text', 'message' => 'textarea', 'accent' => 'color', 'signatory' => 'text'], ['name' => 'required|string|max:255', 'heading' => 'required|string|max:255', 'message' => 'nullable|string|max:2000', 'accent' => 'required|regex:/^#[a-fA-F0-9]{6}
$/', 'signatory' => 'required|string|max:255']],
        ];
    }
}

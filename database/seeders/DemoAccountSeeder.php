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
use App\Models\Submission;
use App\Models\User;
use App\Services\LearningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['Admin LMS', 'Instructor', 'Student'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }

            $admin = $this->demoUser('admindemo', 'admindemo', 'Admin Demo', 'admin', 'admindemo@demo.aldeftech.com');
            $instructor = $this->demoUser('instructordemo', 'instructordemo', 'Instructor Demo', 'instructor', 'instructordemo@demo.aldeftech.com', 'Instruktur demo untuk mencoba pembuatan materi, penilaian, dan pengelolaan kelas.');
            $student = $this->demoUser('studentdemo', 'studentdemo', 'Student Demo', 'student', 'studentdemo@demo.aldeftech.com', 'Pelajar demo yang sedang mengikuti kelas contoh ALDEF LMS.');

            $category = Category::updateOrCreate(
                ['slug' => 'demo-digital-skills'],
                ['name' => 'Demo Digital Skills', 'description' => 'Kategori khusus data demo ALDEF LMS.', 'parent_id' => null, 'is_demo' => true],
            );
            $template = CertificateTemplate::updateOrCreate(
                ['name' => 'Demo ALDEF Certificate'],
                ['heading' => 'Certificate of Demo Completion', 'message' => 'Diberikan setelah menyelesaikan kelas demo', 'accent' => '#5653d9', 'signatory' => 'ALDEF Academy Demo', 'signature_mode' => 'upload', 'background_image' => null, 'signature_image' => null, 'is_demo' => true],
            );
            $course = Course::updateOrCreate(
                ['slug' => 'demo-membangun-pengalaman-belajar-digital'],
                [
                    'title' => 'Demo: Membangun Pengalaman Belajar Digital',
                    'category_id' => $category->id,
                    'instructor_id' => $instructor->id,
                    'certificate_template_id' => $template->id,
                    'description' => 'Kelas contoh untuk mengeksplorasi alur belajar, progres, kuis, tugas, diskusi, dan penilaian di ALDEF LMS.',
                    'objectives' => "Mengenal alur kelas digital.\nMenyelesaikan lesson dan kuis.\nMengirim tugas untuk dinilai instruktur.",
                    'requirements' => 'Tidak ada prasyarat. Gunakan akun demo untuk mencoba seluruh fitur.',
                    'audience' => 'Pengguna yang ingin melihat pengalaman ALDEF LMS dari sisi instructor dan student.',
                    'level' => 'beginner',
                    'tags' => 'demo, lms, digital learning',
                    'thumbnail' => null,
                    'status' => 'published',
                    'featured' => true,
                    'is_demo' => true,
                ],
            );

            $section = Section::updateOrCreate(
                ['course_id' => $course->id, 'position' => 0],
                ['title' => 'Mulai perjalanan demo'],
            );
            $lessons = collect([
                ['position' => 0, 'title' => 'Selamat datang di ALDEF LMS', 'duration' => 7, 'preview' => true, 'content' => "Selamat datang di kelas demo ALDEF LMS.\n\nDi sini Anda dapat melihat bagaimana materi disusun, progres direkam, dan aktivitas belajar terhubung dengan assessment serta tugas."],
                ['position' => 1, 'title' => 'Merancang pembelajaran yang hidup', 'duration' => 12, 'preview' => false, 'content' => "Pengalaman belajar yang baik memiliki tujuan yang jelas, materi ringkas, aktivitas bermakna, dan umpan balik yang membantu.\n\nCobalah menandai lesson ini selesai lalu lanjutkan ke kuis dan tugas contoh."],
                ['position' => 2, 'title' => 'Langkah berikutnya', 'duration' => 8, 'preview' => false, 'content' => "Gunakan dashboard untuk memantau progres. Instructor dapat meninjau submission dan Admin LMS dapat melihat statistik seluruh ruang demo."],
            ])->map(fn (array $lesson) => Lesson::updateOrCreate(
                ['section_id' => $section->id, 'position' => $lesson['position']],
                $lesson + ['type' => 'text', 'url' => null, 'attachment' => null],
            ));

            $quiz = Quiz::updateOrCreate(
                ['course_id' => $course->id, 'title' => 'Kuis Demo Pengalaman Belajar'],
                ['lesson_id' => $lessons[1]->id, 'type' => 'quiz', 'passing_grade' => 70, 'time_limit' => 15, 'max_attempts' => 3, 'required' => true],
            );
            $questionOne = Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'prompt' => 'Apa yang membuat pengalaman belajar digital lebih efektif?'],
                ['type' => 'multiple_choice', 'media_type' => 'text', 'media_path' => null, 'media_url' => null, 'options' => ['Tujuan jelas dan umpan balik', 'Materi tanpa struktur', 'Tidak ada aktivitas'], 'answer' => 'Tujuan jelas dan umpan balik', 'points' => 1],
            );
            $questionTwo = Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'prompt' => 'Progres lesson membantu pelajar melanjutkan pembelajaran dari posisi terakhir.'],
                ['type' => 'multiple_choice', 'media_type' => 'text', 'media_path' => null, 'media_url' => null, 'options' => ['Benar', 'Salah'], 'answer' => 'Benar', 'points' => 1],
            );
            $assignment = Assignment::updateOrCreate(
                ['course_id' => $course->id, 'title' => 'Tugas Demo: Rencana Aktivitas Belajar'],
                ['instructions' => 'Tuliskan satu tujuan belajar dan satu aktivitas singkat yang membantu mencapai tujuan tersebut.', 'due_at' => now()->addDays(7)->startOfHour(), 'required' => true],
            );
            $enrollment = Enrollment::updateOrCreate(
                ['user_id' => $student->id, 'course_id' => $course->id],
                ['status' => 'active', 'last_lesson_id' => $lessons[1]->id, 'completed_at' => null],
            );
            foreach ($lessons->take(2) as $lesson) {
                LessonCompletion::updateOrCreate(
                    ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
                    ['completed_at' => now()->subHours(2)],
                );
            }
            QuizAttempt::updateOrCreate(
                ['quiz_id' => $quiz->id, 'user_id' => $student->id],
                [
                    'started_at' => now()->subHour()->startOfMinute(),
                    'answers' => [$questionOne->id => $questionOne->answer, $questionTwo->id => $questionTwo->answer],
                    'question_scores' => [$questionOne->id => 1, $questionTwo->id => 1],
                    'score' => 100,
                    'passed' => true,
                    'grading_status' => 'graded',
                    'feedback' => 'Jawaban sangat baik. Lanjutkan ke lesson berikutnya.',
                    'submitted_at' => now()->subMinutes(50),
                ],
            );
            Submission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $student->id],
                ['file' => null, 'notes' => 'Tujuan: memahami alur kelas digital. Aktivitas: menyelesaikan lesson, kuis, lalu merefleksikan hasilnya.', 'grade' => 90, 'feedback' => 'Contoh rencana sudah jelas dan dapat dilaksanakan.', 'status' => 'graded'],
            );
            Announcement::updateOrCreate(
                ['course_id' => $course->id, 'user_id' => $admin->id, 'title' => 'Selamat datang di ruang demo'],
                ['body' => 'Silakan mencoba fitur sesuai role. Seluruh data demo dikembalikan ke kondisi awal setiap 24 jam.'],
            );

            app(LearningService::class)->refresh($enrollment);
        });
    }

    private function demoUser(string $username, string $password, string $name, string $role, string $email, ?string $bio = null): User
    {
        $user = User::updateOrCreate(
            ['username' => $username],
            ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role, 'organization_id' => null, 'organization' => null, 'active' => true, 'is_demo' => true, 'bio' => $bio, 'avatar' => null, 'email_verified_at' => now(), 'remember_token' => null],
        );
        $user->syncRoles(User::ROLES[$role]);

        return $user;
    }
}

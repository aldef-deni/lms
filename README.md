# ALDEF LMS

A Laravel 12 learning management system for **lms.aldeftech.com**, built with Blade, Tailwind CSS, native Laravel authentication, MySQL relationships, and Aldef Tech branding.

## Included workflows

- Public academy, searchable course catalog, course outlines and preview lessons.
- Login, registration, password reset, profiles, password changes, active-account enforcement and rate limiting.
- Super Admin, Admin LMS, Instructor, Student and Organization roles. Registration always creates a Student. Administrators manage users; only Super Admin can assign administrative roles or change system settings. Instructors can manage only their own content.
- Categories/subcategories, course metadata and thumbnails, chapters, ordered lessons, private attachments, video, safe-provider embeds, documents, links and live meeting links.
- Student and administrative enrollments, lesson/chapter progress, resume learning and course completion.
- Question bank, course/lesson quizzes and exams, server-enforced time limits, attempt limits, score history, multiple choice, true/false and case-insensitive exact-match short answers.
- Assignment uploads, deadlines, mentor grading and feedback. Required assignments need a grade of at least 70 for certification.
- Automatic certificates after all lessons and required assessments are completed. Unique certificate numbers, public verification, local QR generation, landscape PDF downloads, configurable templates and revocation.
- Announcements, course discussions/replies, role-aware reports, system settings and administrative activity logs. Laravel database notifications schema is ready for future event-specific notifications.

## Installation / deployment

Serve **only the `public/` directory** through aaPanel. PHP 8.2+ and Node 20.19+ (or 22.12+) are required. Enable the standard Laravel PHP extensions plus DOM/XML, mbstring and GD for PDF/image handling.

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

Keep the existing `.env` and `APP_KEY`. Configure `APP_NAME="ALDEF LMS"`, `APP_URL=https://lms.aldeftech.com`, `APP_ENV=production`, `APP_DEBUG=false`, MySQL credentials and HTTPS session cookies. Configure a real mail transport for password reset delivery. The log mailer is useful for development only. Keep `storage/` and `bootstrap/cache/` writable by the PHP service account, and restrict `.env` access.

### Account provisioning

Set these private environment variables before seeding:

```dotenv
LMS_ADMIN_EMAIL=admin@aldeftech.com
LMS_ADMIN_PASSWORD=<unique password of at least 12 characters>
```

The main seeder creates the Super Admin only when its private password is configured. Run `php artisan config:clear` after changing provisioning variables, then `php artisan db:seed --force`.

### Demo accounts and automatic reset

Create or restore the isolated demo workspace with:

```sh
php artisan db:seed --class=Database\\Seeders\\DemoAccountSeeder --force
```

| Role | Username | Password |
| --- | --- | --- |
| Admin LMS | `admindemo` | `admindemo` |
| Instructor | `instructordemo` | `instructordemo` |
| Student | `studentdemo` | `studentdemo` |

All three users carry the `users.is_demo` flag. Demo-owned courses, learning history, quiz attempts, assignment submissions, discussions, grading, certificates, uploads, activity, and profile changes are cleared and reseeded by `php artisan demo:reset`. Production users and production-owned data are outside the demo query scope.

The Laravel scheduler registers `demo:reset` with `daily()` and prevents overlapping runs. Add this cron task in aaPanel so Laravel can dispatch it:

```cron
* * * * * cd /www/wwwroot/lms.aldeftech.com && php artisan schedule:run >> /dev/null 2>&1
```

The cron expression runs the scheduler every minute; the reset command itself executes only once per day according to the application timezone.

## Operating the academy

1. Create a category and course. Assign an instructor and certificate template.
2. Add ordered chapters and lessons, then assessments/questions and assignments.
3. Publish the course. Students can enroll, or an administrator can create an enrollment.
4. Review assignments under **Submission review**. View learners and completion in **Reports & analytics**.
5. Certificates issue automatically once eligibility is met. Public verification uses `/verify/{token}` or a certificate number entered at `/verify`.

Lesson text is rendered as escaped plain text. Embed URLs are restricted to YouTube and Vimeo hosts. Uploads use MIME validation; lesson and assignment files are stored privately and downloaded through authorized routes. Thumbnails alone use public storage.

Live lessons link to an external meeting provider; the LMS does not host video conferences. Short-answer scoring uses exact matching, not manual grading or AI. PDF templates support heading, message, accent color and signatory rather than an arbitrary HTML editor. The Organization role currently uses the student learning experience; company billing and group administration are not included.

## Verification and Git

No tests were created and no test suites were run for this implementation. Deployment verification is limited to migrations, Blade compilation, route registration, asset compilation, PHP syntax and certificate rendering. Full interactive acceptance and mail delivery verification remain deployment responsibilities.

All commits must use **Deni Afrizal <deniafrizal2904@gmail.com>**, with no co-author lines.

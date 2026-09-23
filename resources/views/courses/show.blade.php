@extends('layouts.public')

@section('title', $course->title)

@section('content')
<section class="course-detail-hero">
    <div class="course-hero-orb one"></div>
    <div class="course-hero-orb two"></div>
    <div class="container course-detail-grid">
        <div class="course-detail-copy">
            <a class="course-back" href="/courses">← Back to course collection</a>
            <div class="course-labels">
                <span class="detail-level">{{ ucfirst($course->level) }}</span>
                <span>{{ $course->category?->name ?? 'Professional learning' }}</span>
            </div>
            <h1>{{ $course->title }}</h1>
            <p>{{ Str::limit($course->description, 260) }}</p>
            <div class="course-instructor">
                <x-user-avatar :user="$course->instructor" />
                <span><small>Guided by</small><strong>{{ $course->instructor->name }}</strong></span>
            </div>
        </div>
        <div class="course-hero-card">
            <div class="course-hero-visual tone-{{ $course->id % 3 }}">
                @if($course->thumbnail)
                    <img src="{{ asset('storage/'.$course->thumbnail) }}" alt="{{ $course->title }}">
                @endif
                <img class="course-hero-logo" src="{{ asset('assets/logo/aldef-landscape02.png') }}" alt="Aldef Tech">
                <span>{{ $course->category?->name ?? 'ALDEF Academy' }}</span>
            </div>
            <div class="course-hero-stats">
                <div><strong>{{ $course->lessons_count }}</strong><span>Lessons</span></div>
                <div><strong>{{ $course->sections->count() }}</strong><span>Chapters</span></div>
                <div><strong>{{ $course->quizzes_count + $course->assignments_count }}</strong><span>Activities</span></div>
            </div>
        </div>
    </div>
</section>

<section class="course-detail-section">
    <div class="container course-content-grid">
        <main class="course-main-column">
            <div class="course-story-card">
                <span class="eyebrow">ABOUT THIS COURSE</span>
                <h2>Build capability that moves with you.</h2>
                <div class="course-rich-copy">{{ $course->description }}</div>
            </div>

            @php
                $detailBlocks = [
                    'objectives' => ['What you’ll learn', 'check'],
                    'requirements' => ['Before you begin', 'book'],
                    'audience' => ['Who this is for', 'users'],
                ];
            @endphp
            <div class="course-detail-blocks">
                @foreach($detailBlocks as $field => [$label, $icon])
                    @if($course->$field)
                        <article class="course-info-block">
                            <span class="course-info-icon"><x-icon :name="$icon" /></span>
                            <div><h3>{{ $label }}</h3><div class="course-rich-copy compact">{{ $course->$field }}</div></div>
                        </article>
                    @endif
                @endforeach
            </div>

            <div class="curriculum-card">
                <div class="curriculum-heading">
                    <div><span class="eyebrow">YOUR LEARNING JOURNEY</span><h2>A clear path, lesson by lesson.</h2></div>
                    <span>{{ $course->sections->count() }} chapters · {{ $course->lessons_count }} lessons</span>
                </div>
                @forelse($course->sections as $section)
                    <section class="curriculum-section">
                        <div class="curriculum-section-title">
                            <span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div><small>Chapter {{ $loop->iteration }}</small><h3>{{ $section->title }}</h3></div>
                            <span>{{ $section->lessons->count() }} {{ Str::plural('lesson', $section->lessons->count()) }}</span>
                        </div>
                        @foreach($section->lessons as $lesson)
                            <div class="curriculum-lesson">
                                <span class="lesson-marker"><x-icon name="book" /></span>
                                <div><strong>{{ $lesson->title }}</strong><small>{{ ucfirst($lesson->type) }} · {{ $lesson->duration }} min</small></div>
                                @if($lesson->preview)
                                    <a class="preview-link" href="/preview/{{ $lesson->id }}">Preview <x-icon name="arrow" /></a>
                                @else
                                    <span class="locked-label">Enrolled</span>
                                @endif
                            </div>
                        @endforeach
                    </section>
                @empty
                    <div class="empty"><strong>Curriculum is being prepared.</strong>Lessons will appear here soon.</div>
                @endforelse
            </div>
        </main>

        <aside class="course-enroll-card">
            <span class="eyebrow">YOUR NEXT CHAPTER</span>
            <h2>Turn learning into progress.</h2>
            <p>Get structured lessons, practical activities, progress tracking, and a verifiable certificate.</p>

            @auth
                @if(auth()->user()->hasRole('Student'))
                    @if($enrollment && in_array($enrollment->status, ['active', 'completed']))
                        <a class="btn course-primary-cta" href="/learn/{{ $course->id }}">{{ $enrollment->status === 'completed' ? 'Revisit course' : 'Continue learning' }} <x-icon name="arrow" /></a>
                        <div class="enrollment-progress"><div><span>Course progress</span><strong>{{ $enrollment->progress }}%</strong></div><div class="progress"><span style="width:{{ $enrollment->progress }}%"></span></div></div>
                    @elseif($enrollment?->status === 'cancelled')
                        <div class="course-notice">Enrollment is inactive. Contact your administrator to continue.</div>
                    @else
                        <form method="POST" action="/courses/{{ $course->id }}/enroll">
                            @csrf
                            <button class="btn course-primary-cta" type="submit">Start learning <x-icon name="arrow" /></button>
                        </form>
                    @endif
                @elseif(auth()->user()->isAdmin() || auth()->id() === $course->instructor_id)
                    <a class="btn course-primary-cta" href="/manage/courses/{{ $course->id }}/edit">Manage this course <x-icon name="arrow" /></a>
                @else
                    <a class="btn course-primary-cta" href="{{ auth()->user()->dashboardPath() }}">Go to your workspace <x-icon name="arrow" /></a>
                @endif
            @else
                <a class="btn course-primary-cta" href="/register">Join the academy <x-icon name="arrow" /></a>
                <p class="signin-note">Already a member? <a href="/login">Sign in</a></p>
            @endauth

            <div class="course-includes">
                <strong>Included in this journey</strong>
                <ul>
                    <li><x-icon name="check" /> Self-paced expert learning</li>
                    <li><x-icon name="check" /> Practical assessments</li>
                    <li><x-icon name="check" /> Course discussions</li>
                    <li><x-icon name="check" /> Certificate eligibility</li>
                </ul>
            </div>
            @if($course->tags)
                <div class="course-tags"><small>Topics</small>@foreach(array_filter(array_map('trim', explode(',', $course->tags))) as $tag)<span>{{ $tag }}</span>@endforeach</div>
            @endif
        </aside>
    </div>
</section>
@endsection

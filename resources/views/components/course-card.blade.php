@php
    $tone = $course->id % 3;
    $lessonCount = $course->lessons_count ?? $course->lessons()->count();
    $enrollmentCount = $course->enrollments_count ?? $course->enrollments()->count();
@endphp

<article class="premium-course-card">
    <a class="premium-course-cover tone-{{ $tone }}" href="{{ route('courses.show', $course->slug) }}">
        @if($course->thumbnail)
            <img src="{{ asset('storage/'.$course->thumbnail) }}" alt="{{ $course->title }}" loading="lazy">
        @endif
        <span class="cover-shine"></span>
        <span class="course-number">ALDEF / {{ str_pad($course->id, 2, '0', STR_PAD_LEFT) }}</span>
        <span class="course-category">{{ $course->category?->name ?? 'Professional learning' }}</span>
        @if($course->featured)
            <span class="featured-pill">Featured</span>
        @endif
    </a>
    <div class="premium-course-body">
        <div class="course-meta-row">
            <span class="level-pill">{{ ucfirst($course->level) }}</span>
            <span>{{ $lessonCount }} {{ Str::plural('lesson', $lessonCount) }}</span>
            <span>{{ $enrollmentCount }} {{ Str::plural('learner', $enrollmentCount) }}</span>
        </div>
        <h3><a href="{{ route('courses.show', $course->slug) }}">{{ $course->title }}</a></h3>
        <p>{{ Str::limit($course->description, 118) }}</p>
        <div class="course-card-footer">
            <span class="instructor-chip"><x-user-avatar :user="$course->instructor" :size="34" /><span><small>Instructor</small><strong>{{ $course->instructor->name }}</strong></span></span>
            <a class="course-arrow" href="{{ route('courses.show', $course->slug) }}" aria-label="Explore {{ $course->title }}"><x-icon name="arrow" /></a>
        </div>
    </div>
</article>

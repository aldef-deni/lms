@extends('layouts.public')

@section('title', 'Course catalog')

@section('content')
<section class="catalog-hero">
    <div class="container catalog-hero-grid">
        <div>
            <span class="eyebrow">ALDEF LEARNING COLLECTION</span>
            <h1>Knowledge for the work<br><span>you want to do next.</span></h1>
            <p>Expert-led learning paths designed to turn curiosity into practical, career-ready capability.</p>
        </div>
        <div class="catalog-proof" aria-label="Academy highlights">
            <div><strong>{{ $courses->total() }}</strong><span>curated courses</span></div>
            <div><strong>{{ $categories->count() }}</strong><span>learning fields</span></div>
            <div><strong>100%</strong><span>learn at your pace</span></div>
        </div>
    </div>
</section>

<section class="catalog-section">
    <div class="container">
        <form class="catalog-filter" method="GET">
            <div class="catalog-search">
                <span aria-hidden="true">⌕</span>
                <input name="q" value="{{ request('q') }}" placeholder="Search by course title…" aria-label="Search courses">
            </div>
            <select name="category" aria-label="Category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="level" aria-label="Level">
                <option value="">All levels</option>
                @foreach(['beginner', 'intermediate', 'advanced'] as $level)
                    <option value="{{ $level }}" @selected(request('level') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
            <button class="btn" type="submit">Explore courses <x-icon name="arrow" style="width:16px" /></button>
        </form>

        <div class="catalog-heading">
            <div>
                <span class="eyebrow">CURATED FOR YOUR GROWTH</span>
                <h2>{{ request()->hasAny(['q', 'category', 'level']) ? 'Courses matching your search' : 'Choose your next learning path' }}</h2>
            </div>
            <span class="catalog-count">{{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}</span>
        </div>

        <div class="course-grid">
            @forelse($courses as $course)
                @include('components.course-card', ['course' => $course])
            @empty
                <div class="card empty catalog-empty">
                    <span class="catalog-empty-icon"><x-icon name="book" /></span>
                    <strong>No courses found.</strong>
                    <p>Try a different keyword, category, or learning level.</p>
                    <a class="btn secondary small" href="/courses">Clear filters</a>
                </div>
            @endforelse
        </div>

        <div class="catalog-pagination">{{ $courses->links() }}</div>
    </div>
</section>
@endsection

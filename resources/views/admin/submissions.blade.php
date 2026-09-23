@extends('layouts.app')

@section('title', 'Submission review')

@section('content')
<div class="page-head"><div><h1>Submission review</h1><p>Review assignments and essay responses with thoughtful, actionable feedback.</p></div></div>

<div class="review-section-head"><div><span class="eyebrow">QUIZ & EXAM</span><h2>Essay responses</h2></div><span class="badge amber">{{ $essayAttempts->total() }} awaiting review</span></div>
<div class="stack">
    @forelse($essayAttempts as $attempt)
        <article class="card pad essay-review-card">
            <div class="row between"><div><h3>{{ $attempt->quiz->title }}</h3><small>{{ $attempt->user->name }} · {{ $attempt->quiz->course->title }} · {{ $attempt->submitted_at->format('d M Y, H:i') }}</small></div><span class="badge amber">Manual grading</span></div>
            <form method="POST" action="/attempts/{{ $attempt->id }}/grade">
                @csrf @method('PUT')
                @foreach($attempt->quiz->questions as $question)
                    <div class="essay-review-question {{ $question->type !== 'essay' ? 'auto-graded-question' : '' }}">
                        <div class="row between"><strong>{{ $loop->iteration }}. {{ $question->prompt }}</strong><span class="badge">{{ $question->points }} pts</span></div>
                        <div class="essay-response"><small>Student answer</small><p>{{ $attempt->answers[$question->id] ?? 'No answer provided.' }}</p></div>
                        @if($question->type === 'essay')
                            @if($question->answer)<div class="grading-reference"><small>Grading reference</small><p>{{ $question->answer }}</p></div>@endif
                            <div class="field score-field"><label for="score-{{ $attempt->id }}-{{ $question->id }}">Score (0–{{ $question->points }})</label><input id="score-{{ $attempt->id }}-{{ $question->id }}" name="scores[{{ $question->id }}]" type="number" min="0" max="{{ $question->points }}" required></div>
                        @else
                            <small>Automatically graded: {{ (int) ($attempt->question_scores[$question->id] ?? 0) }}/{{ $question->points }} points</small>
                        @endif
                    </div>
                @endforeach
                <div class="field" style="margin-top:20px"><label for="feedback-{{ $attempt->id }}">Overall feedback</label><textarea id="feedback-{{ $attempt->id }}" name="feedback" rows="4"></textarea></div>
                <button class="btn small" style="margin-top:16px">Save essay grade & feedback</button>
            </form>
        </article>
    @empty
        <div class="card empty"><strong>No essays awaiting review.</strong>New essay submissions will appear here automatically.</div>
    @endforelse
    <div class="pagination">{{ $essayAttempts->links() }}</div>
</div>

<div class="review-section-head"><div><span class="eyebrow">ASSIGNMENTS</span><h2>File and written submissions</h2></div></div>
<div class="stack">
    @forelse($submissions as $submission)
        <article class="card pad"><div class="row between"><div><h3>{{ $submission->assignment->title }}</h3><small>{{ $submission->user->name }} · {{ $submission->assignment->course->title }} · {{ $submission->created_at->format('d M Y') }}</small></div><span class="badge {{ $submission->status === 'graded' ? 'green' : 'amber' }}">{{ $submission->status }}</span></div><p style="white-space:pre-wrap;margin:20px 0">{{ $submission->notes }}</p>@if($submission->file)<a class="link" href="/submissions/{{ $submission->id }}/file">Download submission ↗</a>@endif<form class="form-grid" style="margin-top:25px" method="POST" action="/submissions/{{ $submission->id }}">@csrf @method('PUT')<div class="field"><label>Grade (0–100)</label><input name="grade" type="number" min="0" max="100" value="{{ $submission->grade }}" required></div><div class="field"><label>Feedback</label><textarea name="feedback">{{ $submission->feedback }}</textarea></div><div><button class="btn small">Save grade & feedback</button></div></form></article>
    @empty
        <div class="card empty"><strong>You’re all caught up.</strong>Student assignment submissions will appear here.</div>
    @endforelse
    <div class="pagination">{{ $submissions->links() }}</div>
</div>
@endsection

@extends('layouts.app')

@section('title', $quiz->title)

@section('content')
<div class="page-head"><div><a class="link" href="/learn/{{ $quiz->course_id }}">← Back to learning</a><h1 style="margin-top:12px">{{ $quiz->title }}</h1><p>{{ ucfirst($quiz->type) }} · Passing grade {{ $quiz->passing_grade }}% · {{ $quiz->time_limit ? $quiz->time_limit.' minutes' : 'Untimed' }} · {{ $quiz->max_attempts }} attempts allowed</p></div></div>

@if($attempt)
    <form id="quiz-form" class="assessment-form" method="POST" action="/attempts/{{ $attempt->id }}">
        @csrf
        <div class="assessment-head"><div><span class="eyebrow">ASSESSMENT IN PROGRESS</span><h2>Attempt {{ $attempts->count() }}</h2></div>@if($quiz->time_limit)<span class="badge amber" data-deadline="{{ $attempt->started_at->copy()->addMinutes($quiz->time_limit)->toIso8601String() }}">Timer running</span>@endif</div>
        @foreach($quiz->questions as $question)
            <fieldset class="question-card">
                <legend><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span> Question {{ $loop->iteration }}</legend>
                <div class="question-meta"><span>{{ $question->type === 'essay' ? 'Essay' : 'Multiple choice' }}</span><span>{{ $question->points }} {{ Str::plural('point', $question->points) }}</span></div>

                @if($question->media_type === 'image' && $question->media_path)
                    <img class="question-image" src="{{ asset('storage/'.$question->media_path) }}" alt="Question illustration">
                @elseif($question->media_type === 'video')
                    @if($question->media_path)
                        <video class="question-video" controls preload="metadata"><source src="{{ asset('storage/'.$question->media_path) }}"></video>
                    @elseif($question->media_url)
                        @php
                            $host = parse_url($question->media_url, PHP_URL_HOST);
                            $path = trim((string) parse_url($question->media_url, PHP_URL_PATH), '/');
                            $embedUrl = $question->media_url;
                            if (str_contains((string) $host, 'youtu.be')) $embedUrl = 'https://www.youtube-nocookie.com/embed/'.$path;
                            elseif (str_contains((string) $host, 'youtube.com')) { parse_str((string) parse_url($question->media_url, PHP_URL_QUERY), $query); $embedUrl = 'https://www.youtube-nocookie.com/embed/'.($query['v'] ?? basename($path)); }
                            elseif (str_contains((string) $host, 'vimeo.com')) $embedUrl = 'https://player.vimeo.com/video/'.basename($path);
                        @endphp
                        <div class="question-video-frame"><iframe src="{{ $embedUrl }}" title="Question video" allow="accelerometer; autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>
                    @endif
                @endif

                <h3 class="question-prompt">{{ $question->prompt }}</h3>
                @if($question->type === 'essay')
                    <div class="field essay-answer"><label for="answer-{{ $question->id }}">Your answer</label><textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="8" maxlength="10000" placeholder="Write a clear and complete response…"></textarea><small>Your instructor will review and grade this response.</small></div>
                @else
                    <div class="answer-options">
                        @foreach($question->options ?? [] as $option)
                            <label><input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}"><span>{{ $option }}</span></label>
                        @endforeach
                    </div>
                @endif
            </fieldset>
        @endforeach
        <div class="assessment-submit"><div><strong>Ready to submit?</strong><small>Review every answer. Submitted attempts cannot be changed.</small></div><button class="btn" type="submit">Submit assessment <x-icon name="arrow" style="width:17px" /></button></div>
    </form>
@else
    <div class="card pad stack"><span class="eyebrow">READY WHEN YOU ARE</span><h2>Put your knowledge into practice.</h2><p class="muted">Your timer starts when you begin. Essay responses are reviewed by your instructor before a final score is issued.</p>@if($attempts->count() < $quiz->max_attempts)<form method="POST" action="/quizzes/{{ $quiz->id }}/start">@csrf<button class="btn">Begin assessment →</button></form>@else<span class="badge amber">All attempts used</span>@endif</div>
@endif

<div class="card" style="margin-top:24px"><div class="inset"><h3>Your attempt history</h3></div><div class="table-wrap"><table><thead><tr><th>Started</th><th>Score</th><th>Result</th><th>Submitted</th></tr></thead><tbody>
@forelse($attempts as $item)
    <tr><td>{{ $item->started_at->format('d M Y, H:i') }}</td><td>{{ $item->grading_status === 'pending' ? 'Awaiting review' : ($item->score !== null ? $item->score.'%' : '—') }}</td><td><span class="badge {{ $item->passed ? 'green' : 'amber' }}">{{ ! $item->submitted_at ? 'In progress' : ($item->grading_status === 'pending' ? 'Essay review' : ($item->passed ? 'Passed' : 'Not passed')) }}</span></td><td>{{ $item->submitted_at?->format('d M Y, H:i') ?? '—' }}</td></tr>
    @if($item->feedback)<tr><td colspan="4"><strong>Instructor feedback:</strong> {{ $item->feedback }}</td></tr>@endif
@empty
    <tr><td class="empty" colspan="4">Your first attempt starts here.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection

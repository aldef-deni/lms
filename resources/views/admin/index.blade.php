@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="page-head">
    <div><h1>{{ $title }}</h1><p>Keep your academy organized and moving forward.</p></div>
    @unless($resource === 'organizations' && auth()->user()->isCorporateAdmin())
        <a class="btn" href="/manage/{{ $resource }}/create">+ Add {{ Str::singular($title) }}</a>
    @endunless
</div>

<div class="card">
    <form class="pad row wrap" method="GET">
        <input class="search" style="max-width:330px" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($title) }}…" aria-label="Search">
        @foreach(['role', 'status', 'active', 'course_id'] as $filter)
            @if(isset($fields[$filter]))
                <select name="{{ $filter }}" class="search" style="max-width:210px" aria-label="Filter {{ $filter }}">
                    <option value="">All {{ str_replace('_id', '', $filter) }}</option>
                    @foreach(is_array($fields[$filter]) ? $fields[$filter] : ($options[$fields[$filter]] ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected(request($filter) == (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        @endforeach
        <button class="btn secondary">Filter</button><a class="muted" href="/manage/{{ $resource }}">Reset</a>
    </form>

    <div class="table-wrap"><table><thead><tr><th>Record</th>@if($resource === 'users')<th>Email</th><th>Role</th><th>Status</th>@else<th>Details</th><th>Updated</th>@endif<th>Actions</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr>
                <td class="table-title">{{ Str::limit($record->title ?? $record->name ?? $record->prompt ?? ('Enrollment #'.$record->id), 60) }}</td>
                @if($resource === 'users')
                    <td>{{ $record->email }}</td><td><span class="badge">{{ \App\Models\User::ROLES[$record->role] }}</span></td><td><span class="badge {{ $record->active ? 'green' : 'red' }}">{{ $record->active ? 'Active' : 'Inactive' }}</span></td>
                @else
                    <td>
                        @if($resource === 'enrollments') {{ $record->user->name }} · {{ $record->course->title }}
                        @elseif($record->course_id) {{ $options['courses'][$record->course_id] ?? '' }}
                        @elseif($record->section_id) {{ $options['sections'][$record->section_id] ?? '' }}
                        @elseif($record->quiz_id) {{ $options['quizzes'][$record->quiz_id] ?? '' }}
                        @endif
                        @if($record->status)<span class="badge {{ in_array($record->status, ['published', 'active', 'completed']) ? 'green' : 'amber' }}">{{ $record->status }}</span>
                        @elseif($record->type)<span class="badge">{{ str_replace('_', ' ', $record->type) }}</span>
                        @endif
                    </td>
                    <td class="muted">{{ $record->updated_at->format('d M Y') }}</td>
                @endif
                <td><div class="row">
                    <a class="link" href="/manage/{{ $resource }}/{{ $record->id }}/edit">Edit</a>
                    @if($resource === 'courses')
                        <a class="link" href="/courses/{{ $record->slug }}">View</a><a class="link" href="/courses/{{ $record->id }}/discussions">Q&amp;A</a>
                    @endif
                    @unless($resource === 'organizations' && auth()->user()->isCorporateAdmin())
                        <form action="/manage/{{ $resource }}/{{ $record->id }}" method="POST" data-confirm="Delete this record? This action cannot be undone.">@csrf @method('DELETE')<button class="btn danger small">Delete</button></form>
                    @endunless
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty"><strong>No {{ strtolower($title) }} yet.</strong>Add your first record or adjust the filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $records->links() }}</div>
</div>
@endsection

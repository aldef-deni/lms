@extends('layouts.app')

@section('title', ($record->exists ? 'Edit ' : 'Create ').Str::singular($title))

@section('content')
<div class="page-head"><div><a class="link" href="/manage/{{ $resource }}">← {{ $title }}</a><h1 style="margin-top:12px">{{ $record->exists ? 'Edit' : 'Create' }} {{ Str::singular($title) }}</h1></div></div>

<form class="card pad" method="POST" enctype="multipart/form-data" action="/manage/{{ $resource }}{{ $record->exists ? '/'.$record->id : '' }}">
    @csrf
    @if($record->exists) @method('PUT') @endif
    <div class="form-grid">
        @foreach($fields as $field => $type)
            @php
                $value = old($field, $record->$field);
                $value = is_bool($value) ? (int) $value : $value;
                $label = ucfirst(str_replace(['_id', '_'], ['', ' '], $field));
            @endphp
            <div class="field {{ in_array($type, ['textarea', 'signature-pad']) ? 'wide' : '' }}" @if(in_array($field, ['media_path', 'media_url', 'options', 'answer', 'signature_image', 'signature_data'])) data-field-wrap="{{ $field }}" @endif>
                @if($type !== 'signature-pad')<label for="{{ $field }}">{{ $label }}</label>@endif
                @if(is_array($type) || isset($options[$type]))
                    <select id="{{ $field }}" name="{{ $field }}" @if(in_array($field, ['type', 'media_type', 'signature_mode'])) data-control="{{ $field }}" @endif>
                        @if(! is_array($type))<option value="">Select {{ strtolower($label) }}</option>@endif
                        @foreach(is_array($type) ? $type : $options[$type] as $key => $text)
                            <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $text }}</option>
                        @endforeach
                    </select>
                @elseif($type === 'textarea')
                    <textarea name="{{ $field }}" id="{{ $field }}" rows="5">{{ is_array($value) ? implode("\n", $value) : $value }}</textarea>
                @elseif(in_array($type, ['image', 'file', 'question-media', 'certificate-background', 'signature-image']))
                    <input id="{{ $field }}" name="{{ $field }}" type="file" @if(in_array($type, ['image', 'certificate-background', 'signature-image'])) accept="image/png,image/jpeg,image/webp" @elseif($type === 'question-media') accept="image/png,image/jpeg,image/webp,video/mp4,video/webm,video/quicktime" @endif>
                    @if($value)
                        <small>A file is attached. Upload another file to replace it.</small>
                        @if(in_array($type, ['certificate-background', 'signature-image']))<img class="upload-preview" src="{{ asset('storage/'.$value) }}" alt="Current {{ strtolower($label) }}">@endif
                    @endif
                @elseif($type === 'signature-pad')
                    <label>Draw signature</label>
                    <div class="signature-pad-shell" data-signature-pad>
                        <canvas width="900" height="260" aria-label="Signature drawing area"></canvas>
                        <input type="hidden" name="signature_data" value="{{ old('signature_data') }}" data-signature-output>
                        <div class="row between"><small>Draw using a mouse, stylus, or finger.</small><button class="btn secondary small" type="button" data-clear-signature>Clear drawing</button></div>
                    </div>
                @else
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : ($value instanceof \Carbon\Carbon ? $value->format('Y-m-d\TH:i') : $value) }}" @if($type === 'number') min="0" @endif @if($type === 'password') autocomplete="new-password" @endif>
                @endif

                @if($field === 'options')<small>Multiple choice only: one option per line.</small>
                @elseif($field === 'answer')<small>For multiple choice, match one option exactly. For essay, use this as the grading reference or rubric.</small>
                @elseif($field === 'media_url')<small>Video only: use a YouTube or Vimeo URL, or upload the video file above.</small>
                @elseif($field === 'media_path')<small>Upload JPG/PNG/WebP for image questions, or MP4/WebM/MOV for video questions.</small>
                @elseif($field === 'background_image')<small>Optional certificate background, ideally landscape A4 ratio (1.414:1).</small>
                @elseif($field === 'signature_image')<small>Upload a transparent PNG/WebP signature, or select Draw signature.</small>
                @elseif($field === 'password')<small>At least 10 characters. {{ $record->exists ? 'Leave blank to keep the existing password.' : '' }}</small>
                @elseif($field === 'position')<small>Lower numbers appear first.</small>
                @elseif($field === 'url')<small>HTTPS recommended. Embedded videos require a YouTube or Vimeo embed URL.</small>
                @elseif($field === 'time_limit')<small>Time limit in minutes. Leave blank for an untimed assessment.</small>
                @elseif($field === 'due_at')<small>Deadline in {{ config('app.timezone') }}.</small>
                @elseif($field === 'tags')<small>Separate topics with commas.</small>
                @endif
            </div>
        @endforeach
    </div>
    <div class="form-actions"><button class="btn">Save {{ Str::singular($title) }}</button><a class="btn secondary" href="/manage/{{ $resource }}">Cancel</a></div>
</form>
@endsection

@props(['user' => null, 'size' => 40])
<span {{ $attributes->except('style')->merge(['class' => 'avatar user-avatar']) }} style="width:{{ $size }}px;height:{{ $size }}px;{{ $attributes->get('style') }}">
    @if($user?->avatar)
        <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}" loading="lazy">
    @else
        {{ mb_substr($user?->name ?? 'S', 0, 1) }}
    @endif
</span>

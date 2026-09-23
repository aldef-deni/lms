@extends('layouts.auth')

@php($isManagement = ($portal ?? 'learning') === 'management')

@section('title', $isManagement ? 'Management access' : 'Welcome back')

@section('content')
<div class="portal-marker {{ $isManagement ? 'management' : 'learning' }}">
    <x-icon :name="$isManagement ? 'settings' : 'book'" />
    <span>{{ $isManagement ? 'ALDEF MANAGEMENT PORTAL' : 'ALDEF LEARNING PORTAL' }}</span>
</div>
<h1>{{ $isManagement ? 'Manage with clarity.' : 'Welcome back.' }}</h1>
<p class="muted">{{ $isManagement ? 'Secure access for Super Admin, Admin LMS, and Corporate Admin.' : 'Continue teaching or pick up your learning journey.' }}</p>

<form method="POST" action="{{ $isManagement ? route('management.login.submit') : route('login.submit') }}">
    @csrf
    <div class="field"><label for="login">Username or email</label><input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <div class="row between" style="margin:20px 0;font-size:12px"><label><input type="checkbox" name="remember" value="1"> Remember me</label><a class="link" href="/forgot-password">Forgot password?</a></div>
    <button class="btn">{{ $isManagement ? 'Enter management workspace' : 'Sign in to learning' }} →</button>
</form>

<div class="portal-switch">
    @if($isManagement)
        <span>Instructor or Student?</span><a href="/login">Go to learning login →</a>
    @else
        <span>Administrator?</span><a href="/kelola">Go to management portal →</a>
    @endif
</div>
@unless($isManagement)<p style="text-align:center;margin-top:18px" class="muted">New to ALDEF? <a class="link" href="/register">Create an account</a></p>@endunless
@endsection

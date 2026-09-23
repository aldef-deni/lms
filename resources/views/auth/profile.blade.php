@extends('layouts.app')

@section('title', 'My profile')

@section('content')
<div class="page-head"><div><span class="eyebrow" style="color:#7771cc">PERSONAL WORKSPACE</span><h1 style="margin-top:8px">My profile</h1><p>Shape how you appear across the ALDEF learning community.</p></div></div>

<section class="profile-hero">
    <div class="profile-identity">
        <x-user-avatar :user="auth()->user()" :size="104" data-avatar-preview />
        <div><span class="profile-role">{{ auth()->user()->roleName() }}</span><h2>{{ auth()->user()->name }}</h2><p>{{ auth()->user()->email }}</p>@if(auth()->user()->username)<small>@{{ auth()->user()->username }}</small>@endif</div>
    </div>
    <div class="profile-quote"><x-icon name="award" /><span>Your identity travels with every lesson, conversation, and achievement.</span></div>
</section>

<div class="profile-grid">
    <form class="card profile-form" method="POST" action="/profile" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="profile-card-head"><div><span class="eyebrow" style="color:#7771cc">PROFILE DETAILS</span><h2>Personal information</h2></div><span class="profile-step">01</span></div>

        <label class="avatar-upload" for="avatar">
            <x-user-avatar :user="auth()->user()" :size="76" data-avatar-preview />
            <span><strong>Change profile photo</strong><small>JPG, PNG, or WebP · maximum 5 MB</small><span class="avatar-upload-action">Choose image</span></span>
            <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" data-avatar-input>
        </label>

        <div class="profile-fields">
            <div class="field"><label for="name">Full name</label><input id="name" name="name" type="text" value="{{ old('name', auth()->user()->name) }}" required></div>
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required></div>
            <div class="field"><label for="organization">Organization</label><input id="organization" name="organization" type="text" value="{{ old('organization', auth()->user()->organizationRecord?->name ?? auth()->user()->organization) }}"></div>
            <div class="field wide"><label for="bio">About you</label><textarea id="bio" name="bio" rows="6" maxlength="2000" placeholder="Share a little about your experience and learning goals…">{{ old('bio', auth()->user()->bio) }}</textarea><small>Shown as part of your learning identity.</small></div>
        </div>
        <div class="profile-actions"><button class="btn">Save profile <x-icon name="arrow" /></button></div>
    </form>

    <form class="card profile-form security-card" method="POST" action="/profile/password">
        @csrf @method('PUT')
        <div class="profile-card-head"><div><span class="eyebrow" style="color:#7771cc">ACCOUNT SECURITY</span><h2>Update password</h2></div><span class="profile-step">02</span></div>
        <div class="security-note"><span><x-icon name="settings" /></span><p><strong>Keep your account protected.</strong> Use a unique password with a mix of letters and numbers.</p></div>
        <div class="stack">
            @foreach(['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                <div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" type="password" name="{{ $field }}" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"></div>
            @endforeach
        </div>
        <div class="profile-actions"><button class="btn secondary">Update password</button></div>
    </form>
</div>
@endsection

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true], $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'The credentials are incorrect or the account is inactive.'])->onlyInput('email');
        } $r->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()]]);
        $user = User::create([...$data, 'role' => 'student']);
        event(new Registered($user));
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/dashboard');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return back()->with('success', 'If this email is registered, a password reset link has been sent.');
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()]]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET ? redirect('/login')->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function profile(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($r->user()->id)], 'bio' => 'nullable|string|max:2000', 'organization' => 'nullable|string|max:255']);
        if ($data['email'] !== $r->user()->email) {
            $data['email_verified_at'] = null;
        } $r->user()->forceFill($data)->save();

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()]]);
        $r->user()->update(['password' => $data['password'], 'remember_token' => Str::random(60)]);
        $r->session()->regenerate();

        return back()->with('success', 'Password updated.');
    }
}

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
        $data = $r->validate(['login' => 'required|string|max:255', 'password' => 'required|string']);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password'], 'active' => true], $r->boolean('remember'))) {
            return back()->withErrors(['login' => 'Username/email atau password salah, atau akun tidak aktif.'])->onlyInput('login');
        }

        $managementPortal = $r->routeIs('management.login.submit');
        $allowedRoles = $managementPortal ? ['super_admin', 'admin', 'corporate'] : ['instructor', 'student'];
        if (! in_array($r->user()->role, $allowedRoles, true)) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect()->route($managementPortal ? 'management.login' : 'login')->withErrors(['login' => $managementPortal
                ? 'Portal ini khusus Super Admin, Admin LMS, dan Corporate Admin.'
                : 'Portal ini khusus Instructor dan Student. Gunakan portal pengelola untuk akun administrator.'])->onlyInput('login');
        }
        $r->session()->regenerate();

        return redirect($r->user()->dashboardPath());
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()]]);
        $user = User::create([...$data, 'role' => 'student']);
        $user->syncRoles('Student');
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
            abort_if($user->is_demo, 403, 'Demo account passwords are restored automatically.');
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
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($r->user()->id)], 'bio' => 'nullable|string|max:2000', 'organization' => 'nullable|string|max:255', 'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        if ($r->hasFile('avatar')) {
            $data['avatar'] = $r->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }
        if ($data['email'] !== $r->user()->email) {
            $data['email_verified_at'] = null;
        } $r->user()->forceFill($data)->save();

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $r)
    {
        abort_if($r->user()->is_demo, 403, 'Demo account passwords are fixed and restored automatically.');
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()]]);
        $r->user()->update(['password' => $data['password'], 'remember_token' => Str::random(60)]);
        $r->session()->regenerate();

        return back()->with('success', 'Password updated.');
    }
}

<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class ActiveUser { public function handle(Request $request, Closure $next) { if (!$request->user()?->active) { auth()->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login')->withErrors(['email'=>'Your account is inactive. Contact your administrator.']); } return $next($request); } }

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\PortalController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'home'])->name('home');
Route::get('/courses', [PortalController::class, 'catalog'])->name('catalog');
Route::get('/courses/{course:slug}', [PortalController::class, 'course'])->name('courses.show');
Route::get('/preview/{lesson}', [LearningController::class, 'preview']);
Route::get('/verify/{token?}', [CertificateController::class, 'verify'])->name('verify')->middleware('throttle:60,1');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', compact('token')))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update')->middleware('throttle:6,1');
});
Route::middleware(['auth', ActiveUser::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::view('/profile', 'auth.profile');
    Route::put('/profile', [AuthController::class, 'profile']);
    Route::put('/profile/password', [AuthController::class, 'password'])->middleware('throttle:6,1');
    Route::get('/reports', [PortalController::class, 'reports']);
    Route::get('/settings', [ManagementController::class, 'settings']);
    Route::put('/settings', [ManagementController::class, 'saveSettings']);
    Route::get('/activity', [ManagementController::class, 'activity']);
    Route::get('/submissions', [ManagementController::class, 'submissions']);
    Route::put('/submissions/{submission}', [ManagementController::class, 'grade']);
    Route::get('/submissions/{submission}/file', [LearningController::class, 'submissionFile']);
    Route::get('/manage/{resource}', [ManagementController::class, 'index']);
    Route::get('/manage/{resource}/create', [ManagementController::class, 'form']);
    Route::post('/manage/{resource}', [ManagementController::class, 'save']);
    Route::get('/manage/{resource}/{id}/edit', [ManagementController::class, 'form'])->whereNumber('id');
    Route::put('/manage/{resource}/{id}', [ManagementController::class, 'save'])->whereNumber('id');
    Route::delete('/manage/{resource}/{id}', [ManagementController::class, 'destroy'])->whereNumber('id');
    Route::post('/courses/{course}/enroll', [LearningController::class, 'enroll']);
    Route::get('/learn/{course}/{lesson?}', [LearningController::class, 'show']);
    Route::post('/lessons/{lesson}/complete', [LearningController::class, 'complete']);
    Route::get('/lessons/{lesson}/material', [LearningController::class, 'material']);
    Route::get('/quizzes/{quiz}', [LearningController::class, 'quiz']);
    Route::post('/quizzes/{quiz}/start', [LearningController::class, 'start']);
    Route::post('/attempts/{attempt}', [LearningController::class, 'submitQuiz']);
    Route::post('/assignments/{assignment}/submit', [LearningController::class, 'submitAssignment'])->middleware('throttle:10,1');
    Route::post('/courses/{course}/discussions', [LearningController::class, 'discuss'])->middleware('throttle:15,1');
    Route::get('/courses/{course}/discussions', [LearningController::class, 'discussions']);
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::get('/certificates/{certificate}/download',[CertificateController::class, 'download']);
    Route::post('/certificates/{certificate}/revoke',[CertificateController::class, 'revoke']);
});

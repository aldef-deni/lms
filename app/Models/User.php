<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'bio', 'organization'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean'];
    }

    public const ROLES = ['super_admin' => 'Super Admin', 'admin' => 'Admin LMS', 'instructor' => 'Instructor', 'student' => 'Student', 'corporate' => 'Organization'];

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function canTeach(): bool
    {
        return $this->isAdmin() || $this->role === 'instructor';
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}

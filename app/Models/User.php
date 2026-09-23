<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'organization_id', 'active', 'bio', 'organization'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean'];
    }

    public const ROLES = ['super_admin' => 'Super Admin', 'admin' => 'Admin LMS', 'instructor' => 'Instructor', 'student' => 'Student', 'corporate' => 'Corporate Admin'];

    public function roleName(): string
    {
        return self::ROLES[$this->role] ?? 'Student';
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Admin LMS']);
    }

    public function canTeach(): bool
    {
        return $this->isAdmin() || $this->hasRole('Instructor');
    }

    public function isCorporateAdmin(): bool
    {
        return $this->hasRole('Corporate Admin');
    }

    public function dashboardPath(): string
    {
        return match ($this->role) {
            'super_admin' => '/dashboard/super-admin',
            'admin' => '/dashboard/admin',
            'instructor' => '/dashboard/instructor',
            'corporate' => '/dashboard/corporate',
            default => '/dashboard/student',
        };
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function organizationRecord()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}

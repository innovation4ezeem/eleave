<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'employee_no', 'designation', 'employee_status', 'joined_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_at' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHod(): bool
    {
        return $this->role === 'hod';
    }

    public function isAdminOrHod(): bool
    {
        return $this->isAdmin() || $this->isHod();
    }

    public function isActive(): bool
    {
        return $this->employee_status !== null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class)
            ->orderByRaw('end_date IS NULL DESC')
            ->orderByDesc('start_date');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class)->orderByDesc('achieved_on');
    }

    public function approvedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_approvers');
    }

    public function entitledLeaveTypes(): BelongsToMany
    {
        return $this->belongsToMany(LeaveType::class, 'leave_type_user');
    }

    public function canApproveFor(?Department $department): bool
    {
        if ($department === null) {
            return $this->isAdmin();
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isHod()) {
            return $this->department_id === $department->id;
        }

        return false;
    }
}

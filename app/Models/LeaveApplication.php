<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'leave_type_id', 'department_id', 'start_date', 'end_date',
    'start_half', 'end_half', 'total_days', 'reason', 'document_path',
    'document_original_name', 'status', 'prev_status', 'change_action',
    'requested_leave_type_id', 'requested_start_date', 'requested_end_date',
    'requested_start_half', 'requested_end_half', 'requested_total_days',
    'applied_at', 'decided_by', 'decided_at',
    'remarks', 'is_historical', 'leave_year',
])]
class LeaveApplication extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:1',
            'requested_start_date' => 'date',
            'requested_end_date' => 'date',
            'requested_total_days' => 'decimal:1',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'is_historical' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function requestedLeaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'requested_leave_type_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LeaveApplicationLog::class);
    }

    public function hasDocument(): bool
    {
        return ! empty($this->document_path);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning text-dark',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'change_requested' => 'bg-info text-dark',
            default => 'bg-light text-dark',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeOverlapping($query, int $userId, $start, $end, ?string $requestHalf = null)
    {
        $query = $query->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);

        // For single-day AM or PM requests, exclude the complementary half-day on the same date
        if ($requestHalf !== null && $requestHalf !== 'full' && $start === $end) {
            $complement = $requestHalf === 'am' ? 'pm' : 'am';
            $query->where(function ($q) use ($start, $complement) {
                $q->where('start_date', '!=', $start)
                    ->orWhere('end_date', '!=', $start)
                    ->orWhere('start_half', '!=', $complement);
            });
        }

        return $query;
    }
}

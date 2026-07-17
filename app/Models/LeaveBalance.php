<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'leave_type_id', 'leave_year', 'entitlement_days', 'used_days', 'adjustment_days'])]
class LeaveBalance extends Model
{
    protected function casts(): array
    {
        return [
            'entitlement_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'adjustment_days' => 'decimal:1',
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

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class);
    }

    protected function remainingDays(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format((float) $this->entitlement_days + (float) $this->adjustment_days - (float) $this->used_days, 1, '.', ''),
        );
    }
}

<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;

class LeaveBalanceService
{
    public function getOrCreateBalance(User $user, LeaveType $leaveType, int $leaveYear): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'leave_year' => $leaveYear,
            ],
            [
                'entitlement_days' => $leaveType->default_annual_quota,
                'used_days' => 0,
                'adjustment_days' => 0,
            ],
        );
    }

    public function deduct(LeaveBalance $balance, float $days): void
    {
        $balance->increment('used_days', $days);
    }

    public function restore(LeaveBalance $balance, float $days): void
    {
        $balance->decrement('used_days', $days);
    }
}

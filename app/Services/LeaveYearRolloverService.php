<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;

/**
 * At the start of each leave year (1 Feb), unused Annual Leave from the
 * leave year that just ended becomes that employee's Carry Forward Leave
 * entitlement for the new leave year, capped at 5 days.
 */
class LeaveYearRolloverService
{
    public function __construct(private LeaveBalanceService $balanceService)
    {
    }

    public function rolloverUser(User $user, int $newLeaveYear): float
    {
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();
        $carryForwardType = LeaveType::where('code', 'carry_forward')->firstOrFail();

        $previousBalance = LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $annualLeaveType->id)
            ->where('leave_year', $newLeaveYear - 1)
            ->first();

        $remaining = $previousBalance ? (float) $previousBalance->remaining_days : 0.0;
        $carryForwardDays = max(0.0, min(5.0, $remaining));

        $carryForwardBalance = $this->balanceService->getOrCreateBalance($user, $carryForwardType, $newLeaveYear);
        $carryForwardBalance->update(['entitlement_days' => $carryForwardDays]);

        return $carryForwardDays;
    }

    /**
     * @return array<int, float> map of user_id => carry forward days granted
     */
    public function rolloverAll(int $newLeaveYear): array
    {
        $results = [];

        foreach (User::where('is_active', true)->get() as $user) {
            $results[$user->id] = $this->rolloverUser($user, $newLeaveYear);
        }

        return $results;
    }
}

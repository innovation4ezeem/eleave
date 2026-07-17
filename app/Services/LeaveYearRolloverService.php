<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;

class LeaveYearRolloverService
{
    public function __construct(private LeaveBalanceService $balanceService)
    {
    }

    public function rolloverUser(User $user, int $newLeaveYear): float
    {
        $carryForwardType = LeaveType::where('code', 'carry_forward')->firstOrFail();

        $carryForwardDays = $this->estimateCarryForward($user, $newLeaveYear);

        $carryForwardBalance = $this->balanceService->getOrCreateBalance($user, $carryForwardType, $newLeaveYear);
        $carryForwardBalance->update(['entitlement_days' => $carryForwardDays]);

        return $carryForwardDays;
    }

    /**
     * Compute (without persisting) how many Carry Forward days a user would
     * receive for the given leave year: unused Annual + Reward from the prior
     * year, capped at 5 days total. Used both by the real rollover and by the
     * balance screens to show a live estimate for a not-yet-rolled year.
     */
    public function estimateCarryForward(User $user, int $newLeaveYear): float
    {
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();
        $rewardLeaveType = LeaveType::where('code', 'reward')->first();

        $previousYear = $newLeaveYear - 1;

        $annualRemaining = $this->remainingFor($user, $annualLeaveType->id, $previousYear);
        $rewardRemaining = $rewardLeaveType
            ? $this->remainingFor($user, $rewardLeaveType->id, $previousYear)
            : 0.0;

        // Combine unused Annual + Reward, capped at 5 days total.
        return min(5.0, max(0.0, $annualRemaining) + max(0.0, $rewardRemaining));
    }

    private function remainingFor(User $user, int $leaveTypeId, int $leaveYear): float
    {
        $balance = LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_year', $leaveYear)
            ->first();

        return $balance ? (float) $balance->remaining_days : 0.0;
    }

    /**
     * @return array<int, float> map of user_id => carry forward days granted
     */
    public function rolloverAll(int $newLeaveYear): array
    {
        $results = [];

        foreach (User::whereNotNull('employee_status')->get() as $user) {
            // Compute and set Carry Forward from previous year's unused Annual Leave
            $carryForwardDays = $this->rolloverUser($user, $newLeaveYear);

            // Provision only the leave types this user is entitled to (idempotent — won't overwrite existing rows)
            foreach ($user->entitledLeaveTypes()->active()->get() as $leaveType) {
                if ($leaveType->code !== 'carry_forward') {
                    $this->balanceService->getOrCreateBalance($user, $leaveType, $newLeaveYear);
                }
            }

            $results[$user->id] = $carryForwardDays;
        }

        return $results;
    }
}

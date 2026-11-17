<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Models\User;
use App\Support\LeaveYear;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveYearRolloverService
{
    public const CARRY_FORWARD_CAP = 5.0;

    public function __construct(private LeaveBalanceService $balanceService)
    {
    }

    /**
     * The full year-end rollover into $newLeaveYear: set every active user's
     * Carry Forward, provision their new-year balances, then lock (archive)
     * the leave year that just ended. Used by both the 1 Jan scheduled
     * command and the admin "Run rollover" button, and safe to re-run.
     *
     * @return array<int, float> map of user_id => carry forward days granted
     */
    public function run(int $newLeaveYear, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($newLeaveYear, $actorId) {
            $results = $this->rolloverAll($newLeaveYear, $actorId);

            LeaveYearLock::updateOrCreate(
                ['leave_year' => $newLeaveYear - 1],
                ['locked_at' => now(), 'locked_by' => $actorId],
            );

            return $results;
        });
    }

    public function rolloverUser(User $user, int $newLeaveYear, ?int $actorId = null): float
    {
        $carryForwardType = LeaveType::where('code', 'carry_forward')->firstOrFail();

        $carryForwardDays = $this->estimateCarryForward($user, $newLeaveYear);

        $carryForwardBalance = $this->balanceService->getOrCreateBalance($user, $carryForwardType, $newLeaveYear);
        $oldDays = (float) $carryForwardBalance->entitlement_days;

        // On a re-run (e.g. after late approvals in the old year) keep an audit
        // trail of any Carry Forward that changed.
        if (! $carryForwardBalance->wasRecentlyCreated && $oldDays !== $carryForwardDays) {
            LeaveBalanceAdjustment::create([
                'leave_balance_id' => $carryForwardBalance->id,
                'changed_by' => $actorId,
                'field_changed' => 'entitlement_days',
                'old_value' => $oldDays,
                'new_value' => $carryForwardDays,
                'reason' => 'Carry Forward recalculated by year-end rollover',
            ]);
        }

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
        return $this->carryForwardBreakdown($user, $newLeaveYear)['total'];
    }

    /**
     * @return array{annual: float, reward: float, total: float}
     */
    public function carryForwardBreakdown(User $user, int $newLeaveYear): array
    {
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();
        $rewardLeaveType = LeaveType::where('code', 'reward')->first();

        $previousYear = $newLeaveYear - 1;

        $annualRemaining = $this->remainingFor($user, $annualLeaveType->id, $previousYear);
        $rewardRemaining = $rewardLeaveType
            ? $this->remainingFor($user, $rewardLeaveType->id, $previousYear)
            : 0.0;

        // Combine unused Annual + Reward, capped at 5 days total.
        return [
            'annual' => $annualRemaining,
            'reward' => $rewardRemaining,
            'total' => min(self::CARRY_FORWARD_CAP, max(0.0, $annualRemaining) + max(0.0, $rewardRemaining)),
        ];
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
     * @return Collection<int, User>
     */
    public function rolloverUsers(): Collection
    {
        return User::whereNotNull('employee_status')->orderBy('name')->get();
    }

    /**
     * What a rollover into $newLeaveYear would write, per user, without
     * changing anything. "current" is the Carry Forward already stored for
     * that year (null if none yet), "used" how much of it has been taken.
     *
     * @return Collection<int, array{user: User, annual: float, reward: float, current: ?float, used: float, new: float}>
     */
    public function preview(int $newLeaveYear): Collection
    {
        $carryForwardType = LeaveType::where('code', 'carry_forward')->firstOrFail();

        $stored = LeaveBalance::where('leave_type_id', $carryForwardType->id)
            ->where('leave_year', $newLeaveYear)
            ->get()
            ->keyBy('user_id');

        return $this->rolloverUsers()->map(function (User $user) use ($newLeaveYear, $stored) {
            $breakdown = $this->carryForwardBreakdown($user, $newLeaveYear);
            $balance = $stored->get($user->id);

            return [
                'user' => $user,
                'annual' => $breakdown['annual'],
                'reward' => $breakdown['reward'],
                'current' => $balance ? (float) $balance->entitlement_days : null,
                'used' => $balance ? (float) $balance->used_days : 0.0,
                'new' => $breakdown['total'],
            ];
        });
    }

    /**
     * Why the admin button may not run a rollover into $newLeaveYear right
     * now, or null if it may. Only the current leave year can be rolled, and
     * only before its Carry Forward expires on 1 March: rolling a future year
     * would lock the running year early and use unfinished numbers, and
     * re-rolling after expiry would bring expired Carry Forward back.
     */
    public function manualRunBlockedReason(int $newLeaveYear): ?string
    {
        $current = LeaveYear::current();

        if ($newLeaveYear !== $current) {
            return "The rollover can only be run for the current leave year ({$current}), not {$newLeaveYear}.";
        }

        if (now()->greaterThanOrEqualTo(Carbon::create($newLeaveYear, 3, 1)->startOfDay())) {
            return "Carry Forward Leave for {$newLeaveYear} expired on 1 March {$newLeaveYear}, so the rollover can no longer be run.";
        }

        if (LeaveYearLock::isLocked($newLeaveYear)) {
            return "Leave year {$newLeaveYear} is locked, so its Carry Forward cannot be written. Unlock it first.";
        }

        return null;
    }

    /**
     * @return array<int, float> map of user_id => carry forward days granted
     */
    public function rolloverAll(int $newLeaveYear, ?int $actorId = null): array
    {
        $results = [];

        foreach ($this->rolloverUsers() as $user) {
            // Compute and set Carry Forward from previous year's unused Annual Leave
            $carryForwardDays = $this->rolloverUser($user, $newLeaveYear, $actorId);

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

<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveYear;

class LeaveEntitlementService
{
    /**
     * Resolve the entitlement (in days) for a user/leave-type/leave-year.
     *
     * If the leave type has configured years-of-service tiers, the entitlement
     * is the highest tier whose min_years <= the user's whole years of service
     * (measured at the start of the leave year). Otherwise — or if no tier
     * matches — it falls back to the leave type's flat default quota.
     */
    public function entitlementFor(User $user, LeaveType $leaveType, int $leaveYear): float
    {
        $tiers = $leaveType->relationLoaded('entitlementTiers')
            ? $leaveType->entitlementTiers
            : $leaveType->entitlementTiers()->get();

        if ($tiers->isEmpty()) {
            \Log::info("No tiers for {$leaveType->name}");
            return (float) $leaveType->default_annual_quota;
        }

        // Filter tiers by employee status: matching status or generic (null)
        $applicableTiers = $tiers->filter(function ($tier) use ($user) {
            $match = $tier->employee_status === null || $tier->employee_status === $user->employee_status;
            \Log::info("Tier {$tier->id}: status={$tier->employee_status}, user_status={$user->employee_status}, match={$match}");
            return $match;
        });

        \Log::info("User {$user->name}: {$applicableTiers->count()} applicable tiers for {$leaveType->name}");

        if ($applicableTiers->isEmpty()) {
            \Log::info("No applicable tiers, using default");
            return (float) $leaveType->default_annual_quota;
        }

        // For probation/intern, ignore years of service (always use tier 0)
        if (in_array($user->employee_status, ['probation', 'intern'])) {
            $match = $applicableTiers
                ->filter(fn ($tier) => (int) $tier->min_years === 0)
                ->first();
            \Log::info("Probation/Intern: user={$user->name}, status={$user->employee_status}, match={$match?->days}");
        } else {
            $years = $this->yearsOfService($user, $leaveYear);
            \Log::info("Permanent: user={$user->name}, years={$years}, applicable_tiers={$applicableTiers->count()}");
            \Log::info("Applicable tiers: " . $applicableTiers->map(fn($t) => "id={$t->id},min_years={$t->min_years},status={$t->employee_status},days={$t->days}")->implode("|"));

            $match = $applicableTiers
                ->filter(fn ($tier) => (int) $tier->min_years <= $years)
                ->sortByDesc('min_years')
                ->first();
            \Log::info("Final match: " . ($match ? $match->days : 'NULL'));
        }

        return $match
            ? (float) $match->days
            : (float) $leaveType->default_annual_quota;
    }

    public function yearsOfService(User $user, int $leaveYear): int
    {
        if ($user->joined_at === null) {
            return 0;
        }

        $reference = LeaveYear::startDate($leaveYear);

        if ($user->joined_at->greaterThan($reference)) {
            return 0;
        }

        return (int) $user->joined_at->diffInYears($reference);
    }
}

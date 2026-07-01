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
            return (float) $leaveType->default_annual_quota;
        }

        $years = $this->yearsOfService($user, $leaveYear);

        $match = $tiers
            ->filter(fn ($tier) => (int) $tier->min_years <= $years)
            ->sortByDesc('min_years')
            ->first();

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

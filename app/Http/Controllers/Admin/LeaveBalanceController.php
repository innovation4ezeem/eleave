<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeaveBalanceRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeaveEntitlementService;
use App\Services\LeaveYearRolloverService;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function __construct(
        private LeaveBalanceService $leaveBalanceService,
        private LeaveEntitlementService $entitlementService,
    ) {
    }

    public function edit(Request $request, User $user, LeaveYearRolloverService $rollover): View
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $leaveYear = (int) $request->query('leave_year', LeaveYear::current());

        $entitledTypeIds = $user->entitledLeaveTypes()->active()->pluck('leave_types.id')->all();

        foreach ($user->entitledLeaveTypes()->active()->orderBy('sort_order')->get() as $leaveType) {
            $this->leaveBalanceService->getOrCreateBalance($user, $leaveType, $leaveYear);
        }

        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->where('leave_year', $leaveYear)
            ->whereIn('leave_type_id', $entitledTypeIds)
            ->get()
            ->sortBy(fn (LeaveBalance $balance) => $balance->leaveType->sort_order)
            ->values();

        $adjustments = LeaveBalanceAdjustment::with(['leaveBalance.leaveType', 'changedBy'])
            ->whereHas('leaveBalance', fn ($query) => $query->where('user_id', $user->id)->where('leave_year', $leaveYear))
            ->latest('created_at')
            ->limit(20)
            ->get();

        $locked = LeaveYearLock::isLocked($leaveYear);

        // For the immediate next (not-yet-rolled) leave year, show a live estimate
        // of Carry Forward Leave from this year's unused Annual + Reward.
        $carryForwardEstimate = $leaveYear === LeaveYear::current() + 1
            ? $rollover->estimateCarryForward($user, $leaveYear)
            : null;

        return view('admin.balances.edit', compact('user', 'balances', 'leaveYear', 'adjustments', 'locked', 'carryForwardEstimate'));
    }

    public function recalculate(Request $request, User $user): RedirectResponse
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $leaveYear = (int) $request->input('leave_year', LeaveYear::current());

        if (LeaveYearLock::isLocked($leaveYear)) {
            return redirect()->back()->with('error', 'This leave year is locked (archived) and cannot be recalculated.');
        }

        // Recalculate ONLY refreshes entitlement_days from the current entitlement
        // tiers (e.g. after a probation -> permanent change or a tier edit).
        // It never deletes balances and never touches used_days or
        // adjustment_days, so approved leave and manual adjustments are kept.
        // Leave types without tiers (Carry Forward, Replacement, Reward, ...)
        // are granted manually or by the rollover, so they are left alone.
        $changes = [];

        try {
            DB::transaction(function () use ($user, $leaveYear, &$changes) {
                $leaveTypes = $user->entitledLeaveTypes()->active()->with('entitlementTiers')->get();

                foreach ($leaveTypes as $leaveType) {
                    $balance = $this->leaveBalanceService->getOrCreateBalance($user, $leaveType, $leaveYear);

                    if ($balance->wasRecentlyCreated || $leaveType->entitlementTiers->isEmpty()) {
                        continue;
                    }

                    $oldValue = (float) $balance->entitlement_days;
                    $newValue = $this->entitlementService->entitlementFor($user, $leaveType, $leaveYear);

                    if ($oldValue === $newValue) {
                        continue;
                    }

                    LeaveBalanceAdjustment::create([
                        'leave_balance_id' => $balance->id,
                        'changed_by' => auth()->id(),
                        'field_changed' => 'entitlement_days',
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'reason' => 'Recalculated from entitlement tiers',
                    ]);

                    $balance->update(['entitlement_days' => $newValue]);

                    $changes[] = "{$leaveType->name}: ".number_format($oldValue, 1).' → '.number_format($newValue, 1);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Error recalculating balances: '.$e->getMessage());
        }

        $message = $changes === []
            ? 'Entitlements already match the current tiers. Nothing changed.'
            : 'Entitlements recalculated ('.implode('; ', $changes).'). Used days and adjustments were kept.';

        return redirect()->route('admin.users.balances.edit', ['user' => $user, 'leave_year' => $leaveYear])
            ->with('success', $message);
    }

    public function update(UpdateLeaveBalanceRequest $request, User $user): RedirectResponse
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $data = $request->validated();

        if (LeaveYearLock::isLocked((int) $data['leave_year'])) {
            abort(403, 'This leave year is locked (archived) and cannot be edited.');
        }

        DB::transaction(function () use ($data, $user) {
            foreach ($data['balances'] as $row) {
                $balance = LeaveBalance::where('id', $row['id'])->where('user_id', $user->id)->firstOrFail();

                foreach (['entitlement_days', 'used_days', 'adjustment_days'] as $field) {
                    $oldValue = (float) $balance->$field;
                    $newValue = (float) $row[$field];

                    if ($oldValue !== $newValue) {
                        LeaveBalanceAdjustment::create([
                            'leave_balance_id' => $balance->id,
                            'changed_by' => auth()->id(),
                            'field_changed' => $field,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'reason' => $data['reason'] ?? null,
                        ]);

                        $balance->$field = $newValue;
                    }
                }

                if ($balance->isDirty()) {
                    $balance->save();
                }
            }
        });

        return redirect()->route('admin.users.balances.edit', ['user' => $user, 'leave_year' => $data['leave_year']])
            ->with('success', 'Leave balances updated.');
    }
}

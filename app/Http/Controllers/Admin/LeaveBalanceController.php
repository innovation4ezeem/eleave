<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeaveBalanceRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function __construct(private LeaveBalanceService $leaveBalanceService)
    {
    }

    public function edit(Request $request, User $user): View
    {
        $leaveYear = (int) $request->query('leave_year', LeaveYear::current());

        foreach (LeaveType::active()->orderBy('sort_order')->get() as $leaveType) {
            $this->leaveBalanceService->getOrCreateBalance($user, $leaveType, $leaveYear);
        }

        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->where('leave_year', $leaveYear)
            ->get()
            ->sortBy(fn (LeaveBalance $balance) => $balance->leaveType->sort_order)
            ->values();

        $adjustments = LeaveBalanceAdjustment::with(['leaveBalance.leaveType', 'changedBy'])
            ->whereHas('leaveBalance', fn ($query) => $query->where('user_id', $user->id)->where('leave_year', $leaveYear))
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('admin.balances.edit', compact('user', 'balances', 'leaveYear', 'adjustments'));
    }

    public function update(UpdateLeaveBalanceRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

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

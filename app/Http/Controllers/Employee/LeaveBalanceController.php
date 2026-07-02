<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\LeaveYearRolloverService;
use App\Support\LeaveYear;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveYearRolloverService $rollover): View
    {
        $leaveYear = (int) $request->query('leave_year', LeaveYear::current());

        $entitledTypeIds = $request->user()->entitledLeaveTypes()->active()->pluck('leave_types.id')->all();

        $balances = $request->user()->leaveBalances()
            ->with('leaveType')
            ->where('leave_year', $leaveYear)
            ->whereIn('leave_type_id', $entitledTypeIds)
            ->get()
            ->sortBy(fn ($balance) => $balance->leaveType->sort_order);

        // For the immediate next (not-yet-rolled) leave year, show a live estimate
        // of Carry Forward Leave from this year's unused Annual + Reward.
        $carryForwardEstimate = $leaveYear === LeaveYear::current() + 1
            ? $rollover->estimateCarryForward($request->user(), $leaveYear)
            : null;

        return view('employee.balances.index', compact('balances', 'leaveYear', 'carryForwardEstimate'));
    }
}

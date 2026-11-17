<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveYearLock;
use App\Services\LeaveYearRolloverService;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeaveYearController extends Controller
{
    public function index(LeaveYearRolloverService $rollover): View
    {
        $current = LeaveYear::current();

        // Gather every leave year that has data, plus the current and next year.
        $years = collect()
            ->merge(LeaveBalance::distinct()->pluck('leave_year'))
            ->merge(LeaveApplication::distinct()->pluck('leave_year'))
            ->push($current)
            ->push($current + 1)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $lockedYears = LeaveYearLock::pluck('leave_year')->flip();

        // The manual rollover button is offered for the current year only
        // while it may run (1 Jan until Carry Forward expires on 1 March).
        $rolloverAvailable = $rollover->manualRunBlockedReason($current) === null;

        return view('admin.leave-years.index', compact('years', 'lockedYears', 'current', 'rolloverAvailable'));
    }

    public function rolloverPreview(int $leaveYear, LeaveYearRolloverService $rollover): View
    {
        $blockedReason = $rollover->manualRunBlockedReason($leaveYear);
        $rows = $rollover->preview($leaveYear);
        $previousYear = $leaveYear - 1;
        $previousLocked = LeaveYearLock::isLocked($previousYear);

        // Undecided leave in the year being closed: once it is locked these
        // can no longer be approved, so decide them first.
        $undecided = LeaveApplication::with(['user', 'leaveType'])
            ->where('leave_year', $previousYear)
            ->whereIn('status', ['pending', 'change_requested'])
            ->orderBy('start_date')
            ->get();

        return view('admin.leave-years.rollover', compact(
            'leaveYear', 'previousYear', 'previousLocked', 'rows', 'undecided', 'blockedReason',
        ));
    }

    public function rollover(int $leaveYear, LeaveYearRolloverService $rollover): RedirectResponse
    {
        if ($reason = $rollover->manualRunBlockedReason($leaveYear)) {
            return redirect()->route('admin.leave-years.index')->with('error', $reason);
        }

        $results = $rollover->run($leaveYear, auth()->id());
        $previousYear = $leaveYear - 1;

        return redirect()->route('admin.leave-years.index')->with('success', sprintf(
            'Year-end rollover into %d done: %d employee(s), %s Carry Forward day(s) in total. Leave year %d is now locked.',
            $leaveYear,
            count($results),
            number_format(array_sum($results), 1),
            $previousYear,
        ));
    }

    public function lock(int $leaveYear): RedirectResponse
    {
        LeaveYearLock::updateOrCreate(
            ['leave_year' => $leaveYear],
            ['locked_at' => now(), 'locked_by' => auth()->id()],
        );

        return redirect()->route('admin.leave-years.index')
            ->with('success', "Leave year {$leaveYear} locked.");
    }

    public function unlock(int $leaveYear): RedirectResponse
    {
        LeaveYearLock::where('leave_year', $leaveYear)->delete();

        return redirect()->route('admin.leave-years.index')
            ->with('success', "Leave year {$leaveYear} unlocked.");
    }
}

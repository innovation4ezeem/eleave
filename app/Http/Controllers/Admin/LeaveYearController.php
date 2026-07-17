<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveYearLock;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeaveYearController extends Controller
{
    public function index(): View
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

        return view('admin.leave-years.index', compact('years', 'lockedYears', 'current'));
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

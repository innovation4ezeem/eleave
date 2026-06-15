<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Support\LeaveYear;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveReportController extends Controller
{
    public function index(Request $request): View
    {
        $leaveYear = (int) $request->query('leave_year', LeaveYear::current());

        $query = $request->user()->leaveApplications()
            ->with(['leaveType', 'decidedBy'])
            ->where('leave_year', $leaveYear);

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->query('leave_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $applications = $query->orderBy('start_date')->get();

        $totalDaysByStatus = $applications->groupBy('status')
            ->map(fn ($group) => $group->sum('total_days'));

        $leaveTypes = LeaveType::orderBy('sort_order')->get();

        return view('employee.leave.report', [
            'applications' => $applications,
            'leaveTypes' => $leaveTypes,
            'leaveYear' => $leaveYear,
            'totalDaysByStatus' => $totalDaysByStatus,
            'selectedLeaveTypeId' => $request->query('leave_type_id'),
            'selectedStatus' => $request->query('status'),
        ]);
    }
}

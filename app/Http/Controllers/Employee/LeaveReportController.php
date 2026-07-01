<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveYear;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveReportController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $isManagerView = $viewer->isAdminOrHod();

        // Default date range = current leave-year window (1 Jan - 31 Dec).
        $currentLeaveYear = LeaveYear::current();
        $dateFrom = $request->query('date_from') ?: LeaveYear::startDate($currentLeaveYear)->toDateString();
        $dateTo = $request->query('date_to') ?: LeaveYear::endDate($currentLeaveYear)->toDateString();

        $query = LeaveApplication::query()
            ->with(['user' => fn ($q) => $q->withTrashed(), 'leaveType', 'department', 'decidedBy']);

        // Scope by role
        if ($viewer->isAdmin()) {
            // all records
        } elseif ($viewer->isHod()) {
            $query->where('department_id', $viewer->department_id);
        } else {
            $query->where('user_id', $viewer->id);
        }

        // Date range (overlap)
        $query->where('end_date', '>=', $dateFrom)
            ->where('start_date', '<=', $dateTo);

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->query('leave_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($isManagerView && $request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $applications = $query->orderBy('start_date')->get();

        $totalDaysByStatus = $applications->groupBy('status')
            ->map(fn ($group) => $group->sum('total_days'));

        $leaveTypes = LeaveType::orderBy('sort_order')->get();

        // Employee dropdown (manager view only), scoped to HOD's department
        $employees = collect();
        if ($isManagerView) {
            $employeeQuery = User::orderBy('name');
            if ($viewer->isHod()) {
                $employeeQuery->where('department_id', $viewer->department_id);
            }
            $employees = $employeeQuery->get(['id', 'name']);
        }

        return view('employee.leave.report', [
            'applications' => $applications,
            'leaveTypes' => $leaveTypes,
            'totalDaysByStatus' => $totalDaysByStatus,
            'isManagerView' => $isManagerView,
            'employees' => $employees,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedLeaveTypeId' => $request->query('leave_type_id'),
            'selectedStatus' => $request->query('status'),
            'selectedUserId' => $request->query('user_id'),
        ]);
    }
}

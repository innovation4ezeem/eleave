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

        // Base query: role scoping + date-range overlap + filters (no eager loads).
        $base = LeaveApplication::query();

        // Scope by role
        if ($viewer->isAdmin()) {
            // all records
        } elseif ($viewer->isHod()) {
            $base->where('department_id', $viewer->department_id);
        } else {
            $base->where('user_id', $viewer->id);
        }

        // Date range (overlap)
        $base->where('end_date', '>=', $dateFrom)
            ->where('start_date', '<=', $dateTo);

        if ($request->filled('leave_type_id')) {
            $base->where('leave_type_id', $request->query('leave_type_id'));
        }

        if ($request->filled('status')) {
            $base->where('status', $request->query('status'));
        }

        if ($isManagerView && $request->filled('user_id')) {
            $base->where('user_id', $request->query('user_id'));
        }

        // Summary totals computed in SQL over the entire filtered set (not just one page).
        $totalDaysByStatus = (clone $base)
            ->select('status')
            ->selectRaw('SUM(total_days) as total_days')
            ->groupBy('status')
            ->pluck('total_days', 'status');

        // Rows: paginate on screen; load everything only for the dedicated print view.
        $print = $request->boolean('print');
        $rows = (clone $base)
            ->with(['user' => fn ($q) => $q->withTrashed(), 'leaveType', 'department', 'decidedBy'])
            ->orderBy('start_date');

        $applications = $print
            ? $rows->get()
            : $rows->paginate(50)->withQueryString();

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
            'print' => $print,
            'selectedLeaveTypeId' => $request->query('leave_type_id'),
            'selectedStatus' => $request->query('status'),
            'selectedUserId' => $request->query('user_id'),
        ]);
    }
}

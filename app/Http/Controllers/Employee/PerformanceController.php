<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PerformanceEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        $viewer = $request->user();

        // Admins manage/enter scores on the admin page.
        if ($viewer->isAdmin()) {
            return redirect()->route('admin.performance-entries.index');
        }

        $query = PerformanceEntry::with(['user' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('year')
            ->orderByDesc('month');

        $isManagerView = $viewer->isHod();

        if ($isManagerView) {
            // HOD: own entries + entries of employees in their department.
            $deptUserIds = User::where('department_id', $viewer->department_id)->pluck('id')->push($viewer->id);
            $query->whereIn('user_id', $deptUserIds);

            if ($request->filled('user_id') && $deptUserIds->contains((int) $request->input('user_id'))) {
                $query->where('user_id', $request->input('user_id'));
            }

            $employees = User::where('department_id', $viewer->department_id)
                ->where('id', '!=', $viewer->id)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            // Employee: only their own scores.
            $query->where('user_id', $viewer->id);
            $employees = collect();
        }

        $entries = $query->paginate(24)->withQueryString();

        return view('employee.performance.index', [
            'entries' => $entries,
            'isManagerView' => $isManagerView,
            'employees' => $employees,
            'selectedUserId' => $request->input('user_id'),
        ]);
    }
}

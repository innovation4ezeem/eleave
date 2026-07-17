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

        $isManagerView = $viewer->isHod();

        if ($isManagerView) {
            // HOD: own entries + entries of employees in their department.
            $scopedUserIds = User::where('department_id', $viewer->department_id)->pluck('id')->push($viewer->id);

            $employees = User::where('department_id', $viewer->department_id)
                ->where('id', '!=', $viewer->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            // Optional per-employee filter (must be within the department scope).
            if ($request->filled('user_id') && $scopedUserIds->contains((int) $request->input('user_id'))) {
                $scopedUserIds = collect([(int) $request->input('user_id')]);
            }
        } else {
            // Employee: only their own scores.
            $scopedUserIds = collect([$viewer->id]);
            $employees = collect();
        }

        $entries = PerformanceEntry::with(['user' => fn ($q) => $q->withTrashed()])
            ->whereIn('user_id', $scopedUserIds)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(24)
            ->withQueryString();

        // Yearly summary: total and average per employee per year. AVG divides by
        // the number of months that actually have an entry (not by 12).
        $summaries = PerformanceEntry::query()
            ->whereIn('user_id', $scopedUserIds)
            ->selectRaw('user_id, year, COUNT(*) as months, SUM(total_score) as total, AVG(total_score) as average')
            ->groupBy('user_id', 'year')
            ->orderByDesc('year')
            ->get();

        $names = User::withTrashed()
            ->whereIn('id', $summaries->pluck('user_id')->unique())
            ->pluck('name', 'id');

        return view('employee.performance.index', [
            'entries' => $entries,
            'isManagerView' => $isManagerView,
            'employees' => $employees,
            'selectedUserId' => $request->input('user_id'),
            'summaries' => $summaries,
            'names' => $names,
        ]);
    }
}

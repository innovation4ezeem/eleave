<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffAchievementRequest;
use App\Models\StaffAchievement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StaffAchievementController extends Controller
{
    public function store(StoreStaffAchievementRequest $request, User $user): RedirectResponse
    {
        // Authorization handled in StoreStaffAchievementRequest::authorize().
        $user->staffAchievements()->create($request->validated());

        return back()->with('success', 'Achievement added.');
    }

    public function update(StoreStaffAchievementRequest $request, StaffAchievement $staffAchievement): RedirectResponse
    {
        // Authorization handled in StoreStaffAchievementRequest::authorize().
        $staffAchievement->update($request->validated());

        return back()->with('success', 'Achievement updated.');
    }

    public function destroy(StaffAchievement $staffAchievement): RedirectResponse
    {
        // Resolve with trashed so an orphaned record (owner soft-deleted) denies
        // cleanly rather than passing null to the gate.
        Gate::authorize('manage-profile', $staffAchievement->user()->withTrashed()->first());

        $staffAchievement->delete();

        return back()->with('success', 'Achievement removed.');
    }
}

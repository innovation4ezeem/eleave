<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAchievementRequest;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AchievementController extends Controller
{
    public function store(StoreAchievementRequest $request, User $user): RedirectResponse
    {
        // Authorization handled in StoreAchievementRequest::authorize().
        $user->achievements()->create($request->validated());

        return back()->with('success', 'Achievement added.');
    }

    public function update(StoreAchievementRequest $request, Achievement $achievement): RedirectResponse
    {
        // Authorization handled in StoreAchievementRequest::authorize().
        $achievement->update($request->validated());

        return back()->with('success', 'Achievement updated.');
    }

    public function destroy(Achievement $achievement): RedirectResponse
    {
        // Resolve with trashed so an orphaned record (owner soft-deleted) denies
        // cleanly rather than passing null to the gate.
        Gate::authorize('manage-profile', $achievement->user()->withTrashed()->first());

        $achievement->delete();

        return back()->with('success', 'Achievement removed.');
    }
}

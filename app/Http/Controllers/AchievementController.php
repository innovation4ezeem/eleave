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
        Gate::authorize('manage-profile', $user);

        $user->achievements()->create($request->validated());

        return back()->with('success', 'Achievement added.');
    }

    public function update(StoreAchievementRequest $request, Achievement $achievement): RedirectResponse
    {
        Gate::authorize('manage-profile', $achievement->user);

        $achievement->update($request->validated());

        return back()->with('success', 'Achievement updated.');
    }

    public function destroy(Achievement $achievement): RedirectResponse
    {
        Gate::authorize('manage-profile', $achievement->user);

        $achievement->delete();

        return back()->with('success', 'Achievement removed.');
    }
}

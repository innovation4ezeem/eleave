<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkExperienceRequest;
use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WorkExperienceController extends Controller
{
    public function store(StoreWorkExperienceRequest $request, User $user): RedirectResponse
    {
        // Authorization handled in StoreWorkExperienceRequest::authorize().
        $user->workExperiences()->create($request->validated());

        return back()->with('success', 'Working experience added.');
    }

    public function update(StoreWorkExperienceRequest $request, WorkExperience $workExperience): RedirectResponse
    {
        // Authorization handled in StoreWorkExperienceRequest::authorize().
        $workExperience->update($request->validated());

        return back()->with('success', 'Working experience updated.');
    }

    public function destroy(WorkExperience $workExperience): RedirectResponse
    {
        // Resolve with trashed so an orphaned record (owner soft-deleted) denies
        // cleanly rather than passing null to the gate.
        Gate::authorize('manage-profile', $workExperience->user()->withTrashed()->first());

        $workExperience->delete();

        return back()->with('success', 'Working experience removed.');
    }
}

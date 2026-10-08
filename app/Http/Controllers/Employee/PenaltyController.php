<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PenaltyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Collect subdivision IDs this employee belongs to
        $subdivisionIds = $user->subdivisions()->pluck('subdivisions.id')->toArray();

        $penalties = Penalty::with(['department', 'subdivision', 'createdBy'])
            ->where(function ($q) use ($user, $subdivisionIds) {
                // Personal (targeted at this employee directly)
                $q->where('user_id', $user->id)
                  // OR dept-wide (no user, same department, no subdivision scope)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->whereNull('user_id')
                         ->where('department_id', $user->department_id)
                         ->whereNull('subdivision_id');
                  })
                  // OR subdivision-scoped (employee is in that subdivision)
                  ->orWhere(function ($q2) use ($user, $subdivisionIds) {
                      $q2->whereNull('user_id')
                         ->where('department_id', $user->department_id)
                         ->whereIn('subdivision_id', $subdivisionIds);
                  });
            })
            ->orderByDesc('penalty_date')
            ->paginate(15);

        return view('employee.penalties.index', compact('penalties'));
    }

    public function downloadDocument(Penalty $penalty): StreamedResponse
    {
        $this->authorize('viewDocument', $penalty);

        if (! $penalty->hasDocument()) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $penalty->document_path,
            $penalty->document_original_name,
        );
    }
}

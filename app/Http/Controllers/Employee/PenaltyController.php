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
        $penalties = $request->user()->penalties()
            ->with('createdBy')
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

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WarningLetter;
use App\Models\WarningLetterEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only warning letters for HODs and employees, plus the single download
 * route for every role. Issuing, editing and deleting live in Admin\WarningLetterController.
 */
class WarningLetterController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', WarningLetter::class);

        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.warning-letters.index');
        }

        // Explicit columns: the admin-only notes are never loaded for this list.
        $query = WarningLetter::query()
            ->select(['id', 'user_id', 'issued_date', 'level', 'original_filename'])
            ->with('employee:id,name,employee_no,department_id');

        if ($user->isHod()) {
            // Scope in the query, not the view: own letters plus letters of employees in the
            // HOD's own department. A HOD without a department only ever matches their own.
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->department_id !== null) {
                    $q->orWhereIn(
                        'user_id',
                        User::withTrashed()->where('department_id', $user->department_id)->select('id')
                    );
                }
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $letters = $query->orderByDesc('issued_date')->orderByDesc('id')->paginate(15);

        return view('warning-letters.index', compact('letters'));
    }

    public function download(Request $request, WarningLetter $warningLetter): StreamedResponse
    {
        $this->authorize('download', $warningLetter);

        $disk = Storage::disk(config('warning_letters.disk', 'local'));

        if (! $disk->exists($warningLetter->file_path)) {
            abort(404);
        }

        // Every successful download is logged, whoever the user is (admin, HOD or employee).
        WarningLetterEvent::record($warningLetter, $request->user(), WarningLetterEvent::DOWNLOADED);

        return $disk->download($warningLetter->file_path, $warningLetter->original_filename, [
            'Content-Type' => $warningLetter->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

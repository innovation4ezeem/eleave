<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePenaltyRequest;
use App\Http\Requests\Admin\UpdatePenaltyRequest;
use App\Mail\CashPenaltyToCollect;
use App\Mail\PenaltyIssued;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PenaltyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Penalty::with(['user' => fn ($q) => $q->withTrashed(), 'department', 'subdivision', 'createdBy']);

        // HOD only sees their own department's penalties
        if (auth()->user()->isHod()) {
            $query->where('department_id', auth()->user()->department_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->withTrashed()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%");
                })->orWhereHas('department', function ($d) use ($search) {
                    $d->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('penalty_type')) {
            $query->where('penalty_type', $request->input('penalty_type'));
        }

        if ($request->filled('penalty_level')) {
            $query->where('penalty_level', $request->input('penalty_level'));
        }

        if ($request->filled('collection_status')) {
            $query->where('collection_status', $request->input('collection_status'));
        }

        if ($request->filled('date_from')) {
            $query->where('penalty_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('penalty_date', '<=', $request->input('date_to'));
        }

        $penalties = $query->orderByDesc('penalty_date')->paginate(20)->withQueryString();

        return view('admin.penalties.index', compact('penalties'));
    }

    public function create(): View
    {
        $employees    = $this->employeeList();
        $departments  = \App\Models\Department::where('is_active', true)->orderBy('name')->get();
        $subdivisions = \App\Models\Subdivision::with('department')->orderBy('name')->get();

        return view('admin.penalties.create', compact('employees', 'departments', 'subdivisions'));
    }

    public function store(StorePenaltyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $isIndividual = $data['target_type'] === 'individual';
        $user         = $isIndividual ? User::findOrFail($data['user_id']) : null;
        $departmentId = $isIndividual ? $user->department_id : (int) $data['department_id'];

        $this->authorizeDept($departmentId);

        $storePath = $user ? 'penalty-documents/'.$user->id : 'penalty-documents/dept-'.$departmentId;

        $penalty = new Penalty([
            'user_id'          => $user?->id,
            'department_id'    => $departmentId,
            'subdivision_id'   => $data['subdivision_id'] ?? null,
            'penalty_date'     => $data['penalty_date'],
            'mistake'          => $data['mistake'],
            'penalty_type'     => $data['penalty_type'],
            'penalty_level'    => $data['penalty_level'],
            'penalty_charges'  => $data['penalty_type'] === 'cash' ? $data['penalty_charges'] : null,
            'reported_by'      => $data['reported_by'] ?? auth()->user()->name,
            'remarks'          => $data['remarks'] ?? null,
            'created_by'       => auth()->id(),
            'collection_status'=> $data['penalty_type'] === 'cash' ? 'pending' : null,
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $penalty->document_path = $file->store($storePath, 'local');
            $penalty->document_original_name = $file->getClientOriginalName();
        }

        $penalty->save();

        $this->sendPenaltyEmails($penalty);

        return redirect()->route('admin.penalties.index')->with('success', 'Penalty recorded.');
    }

    public function show(Penalty $penalty): View
    {
        $this->authorizeDept($penalty->department_id);

        $penalty->load(['user' => fn ($q) => $q->withTrashed(), 'department', 'subdivision', 'createdBy', 'collectedBy']);

        return view('admin.penalties.show', compact('penalty'));
    }

    public function edit(Penalty $penalty): View
    {
        $this->authorizeDept($penalty->department_id);

        $employees    = $this->employeeList();
        $departments  = \App\Models\Department::where('is_active', true)->orderBy('name')->get();
        $subdivisions = \App\Models\Subdivision::with('department')->orderBy('name')->get();

        return view('admin.penalties.edit', compact('penalty', 'employees', 'departments', 'subdivisions'));
    }

    public function update(UpdatePenaltyRequest $request, Penalty $penalty): RedirectResponse
    {
        $this->authorizeDept($penalty->department_id);

        $data         = $request->validated();
        $isIndividual = $data['target_type'] === 'individual';
        $user         = $isIndividual ? User::findOrFail($data['user_id']) : null;
        $departmentId = $isIndividual ? $user->department_id : (int) $data['department_id'];

        $this->authorizeDept($departmentId);

        $isCash = $data['penalty_type'] === 'cash';

        $penalty->fill([
            'user_id'        => $user?->id,
            'department_id'  => $departmentId,
            'subdivision_id' => $data['subdivision_id'] ?? null,
            'penalty_date'   => $data['penalty_date'],
            'mistake'        => $data['mistake'],
            'penalty_type'   => $data['penalty_type'],
            'penalty_level'  => $data['penalty_level'],
            'penalty_charges'=> $isCash ? $data['penalty_charges'] : null,
            'reported_by'    => $data['reported_by'] ?? auth()->user()->name,
            'remarks'        => $data['remarks'] ?? null,
        ]);

        // Keep collection tracking consistent with the type.
        if (! $isCash) {
            $penalty->collection_status = null;
            $penalty->collected_by = null;
            $penalty->collected_at = null;
        } elseif ($penalty->collection_status === null) {
            $penalty->collection_status = 'pending';
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $penalty->document_path = $file->store('penalty-documents/'.$user->id, 'local');
            $penalty->document_original_name = $file->getClientOriginalName();
        }

        $penalty->save();

        return redirect()->route('admin.penalties.index')->with('success', 'Penalty updated.');
    }

    public function destroy(Penalty $penalty): RedirectResponse
    {
        $this->authorizeDept($penalty->department_id);

        $penalty->delete();

        return redirect()->route('admin.penalties.index')->with('success', 'Penalty deleted.');
    }

    public function markCollected(Penalty $penalty): RedirectResponse
    {
        // Only admins collect cash.
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        if ($penalty->penalty_type !== 'cash' || $penalty->collection_status !== 'pending') {
            abort(404);
        }

        $penalty->update([
            'collection_status' => 'collected',
            'collected_by' => auth()->id(),
            'collected_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Cash penalty marked as collected.');
    }

    private function employeeList()
    {
        return auth()->user()->isHod()
            ? User::where('department_id', auth()->user()->department_id)->orderBy('name')->get()
            : User::orderBy('name')->get();
    }

    private function authorizeDept(?int $departmentId): void
    {
        if (auth()->user()->isHod() && $departmentId !== auth()->user()->department_id) {
            abort(403);
        }
    }

    private function sendPenaltyEmails(Penalty $penalty): void
    {
        $penalty->loadMissing('user');

        // Notify the employee (warning and cash).
        try {
            if ($penalty->user && $penalty->user->email) {
                Mail::to($penalty->user->email)->send(new PenaltyIssued($penalty));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send penalty issued email.', [
                'penalty_id' => $penalty->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify admins to collect cash.
        if ($penalty->isCash()) {
            try {
                $admins = User::where('role', 'admin')->whereNotNull('employee_status')->get();

                if ($admins->isNotEmpty()) {
                    Mail::to($admins->pluck('email')->all())->send(new CashPenaltyToCollect($penalty));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send cash penalty collection email.', [
                    'penalty_id' => $penalty->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

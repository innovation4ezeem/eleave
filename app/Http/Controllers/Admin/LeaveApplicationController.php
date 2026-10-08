<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveRejectRequest;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = LeaveApplication::query()->with(['user' => fn ($q) => $q->withTrashed(), 'leaveType', 'department']);

        // HOD only sees their own department's applications
        if (auth()->user()->isHod()) {
            $query->where('department_id', auth()->user()->department_id);
        }

        $status = $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('end_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('start_date', '<=', $request->input('date_to'));
        }

        $applications = $query->orderByDesc('applied_at')->paginate(20)->withQueryString();

        // Remember the current filter state so approve/reject returns here
        // instead of resetting to the default (unfiltered) list.
        $request->session()->put('applications_filters', array_filter(
            $request->only([
                'search', 'status', 'user_id', 'department_id',
                'leave_type_id', 'date_from', 'date_to', 'page',
            ]),
            fn ($v) => $v !== null && $v !== '',
        ));

        $employees = auth()->user()->isHod()
            ? User::where('department_id', auth()->user()->department_id)->orderBy('name')->get(['id', 'name'])
            : User::orderBy('name')->get(['id', 'name']);
        $departments = auth()->user()->isAdmin()
            ? Department::orderBy('name')->get(['id', 'name'])
            : collect();
        $leaveTypes = LeaveType::orderBy('name')->get(['id', 'name']);

        return view('admin.applications.index', compact('applications', 'employees', 'departments', 'leaveTypes', 'status'));
    }

    public function show(LeaveApplication $leaveApplication): View
    {
        $this->authorize('view', $leaveApplication);

        $leaveApplication->load(['user', 'leaveType', 'requestedLeaveType', 'department', 'decidedBy', 'logs.actor']);

        return view('admin.applications.show', compact('leaveApplication'));
    }

    public function approve(ApproveRejectRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decide', $leaveApplication);

        $service->approve($leaveApplication, $request->user(), $request->validated('remarks'));

        return $this->backToFilteredIndex()->with('success', 'Leave application approved.');
    }

    public function reject(ApproveRejectRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decide', $leaveApplication);

        $service->reject($leaveApplication, $request->user(), $request->validated('remarks'));

        return $this->backToFilteredIndex()->with('success', 'Leave application rejected.');
    }

    public function approveChange(LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decideChange', $leaveApplication);

        $service->approveChange($leaveApplication, auth()->user());

        return $this->backToFilteredIndex()->with('success', 'Change request approved. The application is pending again and any deducted balance was restored.');
    }

    public function rejectChange(ApproveRejectRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decideChange', $leaveApplication);

        $service->rejectChange($leaveApplication, auth()->user(), $request->validated('remarks'));

        return $this->backToFilteredIndex()->with('success', 'Change request rejected. The application was restored to its previous status.');
    }

    /** Redirect to the applications list, restoring the filters cached on the last index visit. */
    private function backToFilteredIndex(): RedirectResponse
    {
        return redirect()->route('admin.applications.index', session('applications_filters', []));
    }

    public function downloadDocument(LeaveApplication $leaveApplication): StreamedResponse
    {
        $this->authorize('viewDocument', $leaveApplication);

        if (! $leaveApplication->hasDocument()) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $leaveApplication->document_path,
            $leaveApplication->document_original_name,
        );
    }

    /** Serve the document inline (Content-Disposition: inline) for in-page preview. */
    public function previewDocument(LeaveApplication $leaveApplication): StreamedResponse
    {
        $this->authorize('viewDocument', $leaveApplication);

        if (! $leaveApplication->hasDocument()) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $leaveApplication->document_path,
            $leaveApplication->document_original_name,
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}

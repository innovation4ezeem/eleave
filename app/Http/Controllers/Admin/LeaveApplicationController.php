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
        $query = LeaveApplication::query()->with(['user', 'leaveType', 'department.approvers']);

        $status = $request->query('status', 'pending');
        if ($status !== '') {
            $query->where('status', $status);
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

        $employees = User::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $leaveTypes = LeaveType::orderBy('name')->get(['id', 'name']);

        return view('admin.applications.index', compact('applications', 'employees', 'departments', 'leaveTypes', 'status'));
    }

    public function show(LeaveApplication $leaveApplication): View
    {
        $this->authorize('view', $leaveApplication);

        $leaveApplication->load(['user', 'leaveType', 'department.approvers', 'decidedBy', 'logs.actor']);

        return view('admin.applications.show', compact('leaveApplication'));
    }

    public function approve(ApproveRejectRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decide', $leaveApplication);

        $service->approve($leaveApplication, $request->user(), $request->validated('remarks'));

        return redirect()->route('admin.applications.index')->with('success', 'Leave application approved.');
    }

    public function reject(ApproveRejectRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('decide', $leaveApplication);

        $service->reject($leaveApplication, $request->user(), $request->validated('remarks'));

        return redirect()->route('admin.applications.index')->with('success', 'Leave application rejected.');
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
}

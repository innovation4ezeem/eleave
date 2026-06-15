<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreLeaveApplicationRequest;
use App\Mail\LeaveApplicationSubmitted;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveDayCalculator;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = $request->user()->leaveApplications()
            ->with('leaveType')
            ->orderByDesc('start_date')
            ->paginate(15);

        return view('employee.leave.index', compact('applications'));
    }

    public function create(Request $request): View
    {
        $leaveTypes = LeaveType::active()->orderBy('sort_order')->get();

        $balances = $request->user()->leaveBalances()
            ->where('leave_year', LeaveYear::current())
            ->get()
            ->keyBy('leave_type_id');

        return view('employee.leave.create', compact('leaveTypes', 'balances'));
    }

    public function store(StoreLeaveApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $calculator = new LeaveDayCalculator();

        $totalDays = $calculator->calculate(
            $data['start_date'],
            $data['end_date'],
            $data['start_half'],
            $data['end_half'],
        );

        $application = new LeaveApplication([
            'user_id' => $request->user()->id,
            'leave_type_id' => $data['leave_type_id'],
            'department_id' => $request->user()->department_id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_half' => $data['start_half'],
            'end_half' => $data['end_half'],
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'status' => 'pending',
            'applied_at' => now(),
            'is_historical' => false,
            'leave_year' => LeaveYear::forDate($data['start_date']),
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('leave-documents/'.$request->user()->id, 'local');
            $application->document_path = $path;
            $application->document_original_name = $file->getClientOriginalName();
        }

        $application->save();

        $application->logs()->create([
            'actor_id' => $request->user()->id,
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        $this->sendSubmissionEmail($application);

        return redirect()->route('leave.index')->with('success', 'Leave application submitted successfully.');
    }

    public function show(LeaveApplication $leaveApplication): View
    {
        $this->authorize('view', $leaveApplication);

        $leaveApplication->load(['leaveType', 'decidedBy', 'logs.actor']);

        return view('employee.leave.show', compact('leaveApplication'));
    }

    public function cancel(LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorize('cancel', $leaveApplication);

        $leaveApplication->update(['status' => 'cancelled']);

        $leaveApplication->logs()->create([
            'actor_id' => auth()->id(),
            'action' => 'cancelled',
            'from_status' => 'pending',
            'to_status' => 'cancelled',
        ]);

        return redirect()->route('leave.index')->with('success', 'Leave application cancelled.');
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

    private function sendSubmissionEmail(LeaveApplication $application): void
    {
        try {
            $application->loadMissing(['user', 'leaveType', 'department.approvers']);

            $recipients = $application->department?->approvers ?? collect();

            if ($recipients->isEmpty()) {
                $recipients = User::where('role', 'admin')->where('is_active', true)->get();
            }

            if ($recipients->isEmpty()) {
                return;
            }

            Mail::to($recipients->pluck('email')->all())->send(new LeaveApplicationSubmitted($application));
        } catch (\Throwable $e) {
            Log::error('Failed to send leave application submission email.', [
                'leave_application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\RequestLeaveChangeRequest;
use App\Http\Requests\Employee\StoreLeaveApplicationRequest;
use App\Mail\LeaveApplicationSubmitted;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationApprovalService;
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
        $query = $request->user()->leaveApplications()->with('leaveType');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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

        $applications = $query->orderByDesc('start_date')->paginate(15)->withQueryString();

        $leaveTypes = LeaveType::orderBy('sort_order')->get();

        return view('employee.leave.index', compact('applications', 'leaveTypes'));
    }

    public function create(Request $request): View
    {
        $leaveTypes = $request->user()->entitledLeaveTypes()->active()->orderBy('sort_order')->get();

        $balances = $request->user()->leaveBalances()
            ->where('leave_year', LeaveYear::current())
            ->whereIn('leave_type_id', $leaveTypes->pluck('id'))
            ->get()
            ->keyBy('leave_type_id');

        return view('employee.leave.create', compact('leaveTypes', 'balances'));
    }

    public function store(StoreLeaveApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['split_leave'])) {
            return $this->storeSplit($request, $data);
        }

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

    private function storeSplit(StoreLeaveApplicationRequest $request, array $data): RedirectResponse
    {
        $date = $data['start_date'];
        $leaveYear = LeaveYear::forDate($date);
        $userId = $request->user()->id;
        $departmentId = $request->user()->department_id;

        foreach ([
            ['leave_type_id' => $data['leave_type_id'], 'start_half' => 'am'],
            ['leave_type_id' => $data['leave_type_id_pm'], 'start_half' => 'pm'],
        ] as $half) {
            $application = LeaveApplication::create([
                'user_id' => $userId,
                'leave_type_id' => $half['leave_type_id'],
                'department_id' => $departmentId,
                'start_date' => $date,
                'end_date' => $date,
                'start_half' => $half['start_half'],
                'end_half' => 'full',
                'total_days' => 0.5,
                'reason' => $data['reason'],
                'status' => 'pending',
                'applied_at' => now(),
                'is_historical' => false,
                'leave_year' => $leaveYear,
            ]);

            $application->logs()->create([
                'actor_id' => $userId,
                'action' => 'submitted',
                'from_status' => null,
                'to_status' => 'pending',
            ]);

            $this->sendSubmissionEmail($application);
        }

        return redirect()->route('leave.index')->with('success', 'Split leave application submitted (AM + PM).');
    }

    public function show(LeaveApplication $leaveApplication): View
    {
        $this->authorize('view', $leaveApplication);

        $leaveApplication->load(['leaveType', 'requestedLeaveType', 'decidedBy', 'logs.actor']);

        $changeableLeaveTypes = collect();
        if (auth()->id() === $leaveApplication->user_id && in_array($leaveApplication->status, ['approved', 'rejected'], true)) {
            $changeableLeaveTypes = auth()->user()->entitledLeaveTypes()->active()->orderBy('sort_order')->get();
        }

        return view('employee.leave.show', compact('leaveApplication', 'changeableLeaveTypes'));
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

    public function requestChange(RequestLeaveChangeRequest $request, LeaveApplication $leaveApplication, LeaveApplicationApprovalService $service): RedirectResponse
    {
        $this->authorize('requestChange', $leaveApplication);

        $service->requestChange($leaveApplication, $request->user(), $request->validated());

        return redirect()->route('leave.show', $leaveApplication)
            ->with('success', 'Change request submitted. Your HOD will review it.');
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
            $application->loadMissing(['user', 'leaveType']);

            $recipients = User::where('role', 'hod')
                ->where('department_id', $application->department_id)
                ->where('is_active', true)
                ->get();

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

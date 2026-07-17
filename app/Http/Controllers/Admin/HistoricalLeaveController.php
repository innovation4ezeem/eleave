<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHistoricalLeaveRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationApprovalService;
use App\Services\LeaveDayCalculator;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoricalLeaveController extends Controller
{
    public function __construct(private LeaveApplicationApprovalService $approvalService)
    {
    }

    public function index(Request $request): View
    {
        $query = LeaveApplication::with(['user' => fn ($q) => $q->withTrashed(), 'leaveType'])->where('is_historical', true);

        // HOD only sees their own department's historical records
        if (auth()->user()->isHod()) {
            $query->whereHas('user', fn ($q) => $q->withTrashed()->where('department_id', auth()->user()->department_id));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->withTrashed()
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('leave_year')) {
            $query->where('leave_year', $request->input('leave_year'));
        }

        $records = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $leaveTypes = LeaveType::orderBy('sort_order')->get();

        return view('admin.historical.index', compact('records', 'leaveTypes'));
    }

    public function create(): View
    {
        $employees = auth()->user()->isHod()
            ? User::where('department_id', auth()->user()->department_id)->orderBy('name')->get()
            : User::orderBy('name')->get();
        $leaveTypes = LeaveType::orderBy('sort_order')->get();

        return view('admin.historical.create', compact('employees', 'leaveTypes'));
    }

    public function store(StoreHistoricalLeaveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::findOrFail($data['user_id']);

        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $calculator = new LeaveDayCalculator();
        $totalDays = $calculator->calculate(
            $data['start_date'],
            $data['end_date'],
            $data['start_half'],
            $data['end_half'],
        );

        $application = LeaveApplication::create([
            'user_id' => $user->id,
            'leave_type_id' => $data['leave_type_id'],
            'department_id' => $user->department_id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_half' => $data['start_half'],
            'end_half' => $data['end_half'],
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'status' => 'pending',
            'applied_at' => $data['applied_at'] ?? now(),
            'is_historical' => true,
            'leave_year' => LeaveYear::forDate($data['start_date']),
        ]);

        $application->logs()->create([
            'actor_id' => auth()->id(),
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'pending',
            'notes' => 'Historical record created by '.auth()->user()->name,
        ]);

        match ($data['status']) {
            'approved' => $this->approvalService->approve($application, auth()->user(), $data['remarks'] ?? null),
            'rejected' => $this->approvalService->reject($application, auth()->user(), $data['remarks'] ?? null),
            'cancelled' => $this->cancel($application),
            default => null,
        };

        if (! empty($data['decided_at']) && in_array($data['status'], ['approved', 'rejected'], true)) {
            $application->update(['decided_at' => $data['decided_at']]);
        }

        return redirect()->route('admin.historical.create')->with('success', 'Historical leave record created.');
    }

    private function cancel(LeaveApplication $application): void
    {
        $application->update(['status' => 'cancelled']);

        $application->logs()->create([
            'actor_id' => auth()->id(),
            'action' => 'cancelled',
            'from_status' => 'pending',
            'to_status' => 'cancelled',
            'notes' => 'Historical record created by '.auth()->user()->name,
        ]);
    }
}

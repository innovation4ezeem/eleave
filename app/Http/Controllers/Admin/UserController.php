<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\LeaveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private LeaveBalanceService $leaveBalanceService)
    {
    }

    public function index(Request $request): View
    {
        $query = User::with('department');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('employee_status')) {
            $status = $request->input('employee_status');
            if ($status === 'inactive') {
                $query->whereNull('employee_status');
            } else {
                $query->where('employee_status', $status);
            }
        }

        // HOD only sees their own department's users
        if (auth()->user()->isHod()) {
            $query->where('department_id', auth()->user()->department_id);
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        $departments = Department::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'departments'));
    }

    public function show(User $user): View
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $user->load('department', 'subdivisions', 'workExperiences', 'achievements', 'staffAchievements');

        $leaveYear = LeaveYear::current();

        $entitledTypeIds = $user->entitledLeaveTypes()->active()->pluck('leave_types.id')->all();

        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->where('leave_year', $leaveYear)
            ->whereIn('leave_type_id', $entitledTypeIds)
            ->get()
            ->sortBy(fn (LeaveBalance $balance) => $balance->leaveType->sort_order)
            ->values();

        $applications = LeaveApplication::with(['leaveType', 'department'])
            ->where('user_id', $user->id)
            ->orderByDesc('applied_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.show', compact('user', 'balances', 'leaveYear', 'applications'));
    }

    public function create(): View
    {
        $departments = Department::orderBy('name')->get();
        $leaveTypes = LeaveType::active()->orderBy('sort_order')->get();
        $entitledLeaveTypeIds = $leaveTypes->pluck('id')->all();

        return view('admin.users.create', compact('departments', 'leaveTypes', 'entitledLeaveTypeIds'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $trashed = User::onlyTrashed()->where('email', $data['email'])->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update(Arr::except($data, ['leave_type_ids']));
            $user = $trashed;
            $message = 'User restored and updated.';
        } else {
            $user = User::create(Arr::except($data, ['leave_type_ids']));
            $message = 'User created.';
        }

        // Entitled leave types — default to all active types when none specified.
        $entitledIds = $data['leave_type_ids'] ?? LeaveType::active()->pluck('id')->all();
        $user->entitledLeaveTypes()->sync($entitledIds);

        $leaveYear = LeaveYear::current();
        foreach ($user->entitledLeaveTypes()->active()->get() as $leaveType) {
            $this->leaveBalanceService->getOrCreateBalance($user, $leaveType, $leaveYear);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    public function edit(User $user): View
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $departments = Department::orderBy('name')->get();
        $leaveTypes = LeaveType::active()->orderBy('sort_order')->get();
        $entitledLeaveTypeIds = $user->entitledLeaveTypes()->pluck('leave_types.id')->all();

        $user->load('subdivisions', 'department.subdivisions');

        return view('admin.users.edit', compact('user', 'departments', 'leaveTypes', 'entitledLeaveTypeIds'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $data = $request->validated();

        // Entitled leave types are admin-only; sync separately from the user attributes.
        $leaveTypeIds    = $data['leave_type_ids'] ?? null;
        $subdivisionIds  = $data['subdivision_ids'] ?? [];
        $data = Arr::except($data, ['leave_type_ids', 'subdivision_ids']);

        // HOD cannot change role or move employee to another department
        if (auth()->user()->isHod()) {
            unset($data['role'], $data['department_id']);
        }

        if (empty($data['password'])) {
            $data = Arr::except($data, ['password']);
        }

        $user->update($data);

        if (auth()->user()->isAdmin() && $leaveTypeIds !== null) {
            $user->entitledLeaveTypes()->sync($leaveTypeIds);

            $leaveYear = LeaveYear::current();
            foreach ($user->entitledLeaveTypes()->active()->get() as $leaveType) {
                $this->leaveBalanceService->getOrCreateBalance($user, $leaveType, $leaveYear);
            }
        }

        // Sync subdivisions (admin only — HODs don't reassign teams)
        if (auth()->user()->isAdmin()) {
            $user->subdivisions()->sync($subdivisionIds);
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function disable(User $user): RedirectResponse
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $user->update(['employee_status' => null]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User disabled. They can no longer login.');
    }

    public function enable(User $user): RedirectResponse
    {
        if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
            abort(403);
        }

        $user->update(['employee_status' => 'permanent']);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User enabled. They can now login.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount(['users', 'approvers'])->orderBy('name')->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function create(): View
    {
        $admins = User::where('role', 'admin')->orderBy('name')->get();

        return view('admin.departments.create', compact('admins'));
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $department = Department::create(Arr::except($data, ['approver_ids']));
        $department->approvers()->sync($data['approver_ids'] ?? []);

        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department): View
    {
        $admins = User::where('role', 'admin')->orderBy('name')->get();
        $department->load('approvers');

        return view('admin.departments.edit', compact('department', 'admins'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $department->update(Arr::except($data, ['approver_ids']));
        $department->approvers()->sync($data['approver_ids'] ?? []);

        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'Department deleted.');
    }
}

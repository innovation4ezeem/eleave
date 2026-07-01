@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Add User</h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">Select role</option>
                            <option value="employee" {{ old('role') === 'employee' ? 'selected' : '' }}>Employee</option>
                            <option value="hod" {{ old('role') === 'hod' ? 'selected' : '' }}>HOD (Head of Department)</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" {{ (string) old('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="employee_no" class="form-label">Employee No. (optional)</label>
                        <input type="text" id="employee_no" name="employee_no" value="{{ old('employee_no') }}" class="form-control @error('employee_no') is-invalid @enderror">
                        @error('employee_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="designation" class="form-label">Designation (optional)</label>
                        <input type="text" id="designation" name="designation" value="{{ old('designation') }}" class="form-control @error('designation') is-invalid @enderror">
                        @error('designation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="joined_at" class="form-label">Joined Date (optional)</label>
                        <input type="date" id="joined_at" name="joined_at" value="{{ old('joined_at') }}" class="form-control @error('joined_at') is-invalid @enderror">
                        @error('joined_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3 form-check align-self-center">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Entitled Leave Types</label>
                    <div class="row">
                        @foreach ($leaveTypes as $leaveType)
                            @php
                                $checked = old('leave_type_ids') !== null
                                    ? in_array((string) $leaveType->id, old('leave_type_ids', []))
                                    : in_array($leaveType->id, $entitledLeaveTypeIds);
                            @endphp
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="leave_type_ids[]" value="{{ $leaveType->id }}" id="lt_{{ $leaveType->id }}" {{ $checked ? 'checked' : '' }}>
                                    <label class="form-check-label" for="lt_{{ $leaveType->id }}">{{ $leaveType->name }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Only the leave types ticked here will be available to this employee when applying, and only these will get a balance.</div>
                    @error('leave_type_ids')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit User</h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
    </div>

    @if (!$user->isActive())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <strong>⚠️ This user is disabled</strong> and cannot login. Their leave entitlements are frozen.
            <form method="POST" action="{{ route('admin.users.enable', $user) }}" class="d-inline ms-3">
                @csrf
                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Enable this user?\n\nThey will be set to Permanent status.');">Enable User</button>
            </form>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Leave blank to keep current password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role</label>
                        @if (auth()->user()->isAdmin())
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="employee" {{ old('role', $user->role) === 'employee' ? 'selected' : '' }}>Employee</option>
                                <option value="hod" {{ old('role', $user->role) === 'hod' ? 'selected' : '' }}>HOD</option>
                                <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                        @else
                            <input type="hidden" name="role" value="{{ $user->role }}">
                            <input type="text" class="form-control" value="{{ strtoupper($user->role) }}" disabled>
                        @endif
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        @if (auth()->user()->isAdmin())
                            <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">None</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" {{ (string) old('department_id', $user->department_id) === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="department_id" value="{{ $user->department_id }}">
                            <input type="text" class="form-control" value="{{ $user->department?->name ?? '—' }}" disabled>
                        @endif
                        @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="employee_no" class="form-label">Employee No. (optional)</label>
                        <input type="text" id="employee_no" name="employee_no" value="{{ old('employee_no', $user->employee_no) }}" class="form-control @error('employee_no') is-invalid @enderror">
                        @error('employee_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="designation" class="form-label">Designation (optional)</label>
                        <input type="text" id="designation" name="designation" value="{{ old('designation', $user->designation) }}" class="form-control @error('designation') is-invalid @enderror">
                        @error('designation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="employee_status" class="form-label">Employee Status</label>
                        <select id="employee_status" name="employee_status" class="form-select @error('employee_status') is-invalid @enderror" required>
                            <option value="probation" {{ old('employee_status', $user->employee_status) === 'probation' ? 'selected' : '' }}>Probation</option>
                            <option value="intern" {{ old('employee_status', $user->employee_status) === 'intern' ? 'selected' : '' }}>Intern</option>
                            <option value="permanent" {{ old('employee_status', $user->employee_status) === 'permanent' ? 'selected' : '' }}>Permanent</option>
                        </select>
                        @error('employee_status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="joined_at" class="form-label">Joined Date (optional)</label>
                        <input type="date" id="joined_at" name="joined_at" value="{{ old('joined_at', $user->joined_at?->format('Y-m-d')) }}" class="form-control @error('joined_at') is-invalid @enderror">
                        @error('joined_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if (auth()->user()->isAdmin())
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
                @endif

                <div class="mb-3">
                    <label class="form-label">Subdivisions <span class="text-muted small">(optional — tick all that apply)</span></label>
                    @php
                        $checkedIds = old('subdivision_ids', $user->subdivisions->pluck('id')->toArray());
                        // Show: subdivisions matching this employee's dept + all cross-dept (null dept)
                        $visibleSubs = $allSubdivisions->filter(
                            fn($s) => is_null($s->department_id) || $s->department_id === $user->department_id
                        );
                        $grouped = $visibleSubs->groupBy(fn($s) => $s->department?->name ?? 'General / Cross-Department');
                    @endphp
                    @if ($visibleSubs->isNotEmpty())
                        @foreach ($grouped as $groupName => $subs)
                            <p class="text-muted small mb-1 mt-2">{{ $groupName }}</p>
                            <div class="d-flex flex-wrap gap-2 mb-1">
                                @foreach ($subs as $sub)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="subdivision_ids[]"
                                               value="{{ $sub->id }}"
                                               id="sub_{{ $sub->id }}"
                                               {{ in_array($sub->id, $checkedIds) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sub_{{ $sub->id }}">{{ $sub->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted small mb-0">No subdivisions available for this employee's department.
                            <a href="{{ route('admin.subdivisions.index') }}">Manage subdivisions</a>
                        </p>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>

            @if ($user->isActive())
                <form method="POST" action="{{ route('admin.users.disable', $user) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Disable this user? They will not be able to login.\n\nAre you sure?');">Disable User</button>
                </form>
            @endif
        </div>
    </div>
@endsection

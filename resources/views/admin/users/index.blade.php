@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">User Accounts</h1>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">Add User</a>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, email or employee no.">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['admin', 'hod', 'employee'] as $value)
                        <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>{{ strtoupper($value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employee Status</label>
                <select name="employee_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="probation" {{ request('employee_status') === 'probation' ? 'selected' : '' }}>Probation</option>
                    <option value="intern" {{ request('employee_status') === 'intern' ? 'selected' : '' }}>Intern</option>
                    <option value="permanent" {{ request('employee_status') === 'permanent' ? 'selected' : '' }}>Permanent</option>
                    <option value="inactive" {{ request('employee_status') === 'inactive' ? 'selected' : '' }}>Inactive (Fired)</option>
                </select>
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Employee No.</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge bg-secondary text-uppercase">{{ $user->role }}</span></td>
                            <td>{{ $user->department?->name ?? '—' }}</td>
                            <td>{{ $user->employee_no ?? '—' }}</td>
                            <td>
                                @if ($user->employee_status)
                                    <span class="badge bg-info">{{ ucfirst($user->employee_status) }}</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('admin.users.balances.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Balances</a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                @if (auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
@endsection

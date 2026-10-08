@extends('layouts.app')

@section('title', 'Employee Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ $user->name }}</h1>
        <div>
            <a href="{{ route('admin.warning-letters.index') }}?user_id={{ $user->id }}" class="btn btn-outline-secondary btn-sm">Warning Letters</a>
            <a href="{{ route('admin.users.balances.edit', $user) }}" class="btn btn-outline-secondary btn-sm">Manage Balances</a>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary btn-sm">Edit User</a>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Profile</h2>
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $user->name }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $user->email }}</dd>

                <dt class="col-sm-3">Role</dt>
                <dd class="col-sm-9"><span class="badge bg-secondary text-uppercase">{{ $user->role }}</span></dd>

                <dt class="col-sm-3">Department</dt>
                <dd class="col-sm-9">{{ $user->department?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Employee No.</dt>
                <dd class="col-sm-9">{{ $user->employee_no ?? '—' }}</dd>

                <dt class="col-sm-3">Designation</dt>
                <dd class="col-sm-9">{{ $user->designation ?? '—' }}</dd>

                <dt class="col-sm-3">Joined At</dt>
                <dd class="col-sm-9">{{ $user->joined_at?->format('d M Y') ?? '—' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($user->isActive())
                        <span class="badge bg-info">{{ ucfirst($user->employee_status) }}</span>
                    @else
                        <span class="badge bg-danger">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            @include('partials.work-experiences', ['profileUser' => $user])
        </div>
        <div class="col-lg-4">
            @include('partials.staff-achievements', ['profileUser' => $user])
        </div>
        <div class="col-lg-4">
            @include('partials.achievements', ['profileUser' => $user])
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Leave Balances ({{ $leaveYear }})</h2>
                <a href="{{ route('admin.users.balances.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Manage Balances</a>
            </div>

            @if ($balances->isEmpty())
                <p class="text-muted small mb-0">No leave balances have been set up for this leave year yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th class="text-end">Entitlement</th>
                                <th class="text-end">Adjustment</th>
                                <th class="text-end">Used</th>
                                <th class="text-end">Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($balances as $balance)
                                <tr>
                                    <td>{{ $balance->leaveType->name }}</td>
                                    <td class="text-end">{{ $balance->entitlement_days }}</td>
                                    <td class="text-end">{{ $balance->adjustment_days }}</td>
                                    <td class="text-end">{{ $balance->used_days }}</td>
                                    <td class="text-end"><strong>{{ $balance->remaining_days }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body pb-0">
            <h2 class="h6">Leave Application History</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>{{ $application->leaveType->name }}</td>
                            <td>
                                {{ $application->start_date->format('d M Y') }}
                                @if (! $application->start_date->equalTo($application->end_date))
                                    &ndash; {{ $application->end_date->format('d M Y') }}
                                @endif
                            </td>
                            <td>{{ $application->total_days }}</td>
                            <td><span class="badge {{ $application->statusBadgeClass() }}">{{ ucfirst($application->status) }}</span></td>
                            <td>{{ $application->applied_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.applications.show', $application) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No leave applications found for this employee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $applications->links() }}
    </div>
@endsection

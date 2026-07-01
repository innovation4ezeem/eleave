@extends('layouts.app')

@section('title', 'My Leave Applications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Leave Applications</h1>
        <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">Apply for Leave</a>
    </div>

    <form method="GET" action="{{ route('leave.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $value)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Leave Type</label>
                <select name="leave_type_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}" {{ (string) request('leave_type_id') === (string) $leaveType->id ? 'selected' : '' }}>{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
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
                                @if ($application->start_half !== 'full')
                                    <span class="badge bg-light text-dark">Start: {{ strtoupper($application->start_half) }}</span>
                                @endif
                                @if ($application->end_half !== 'full' && ! $application->start_date->equalTo($application->end_date))
                                    <span class="badge bg-light text-dark">End: {{ strtoupper($application->end_half) }}</span>
                                @endif
                            </td>
                            <td>{{ $application->total_days }}</td>
                            <td><span class="badge {{ $application->statusBadgeClass() }}">{{ ucfirst($application->status) }}</span></td>
                            <td>{{ $application->applied_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('leave.show', $application) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No leave applications yet.</td>
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

@extends('layouts.app')

@section('title', 'Historical Leave Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Historical Leave Records</h1>
        <a href="{{ route('admin.historical.create') }}" class="btn btn-primary btn-sm">Add Historical Record</a>
    </div>

    <form method="GET" action="{{ route('admin.historical.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Employee name or no.">
            </div>
            <div class="col-md-2">
                <label class="form-label">Leave Type</label>
                <select name="leave_type_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}" {{ (string) request('leave_type_id') === (string) $leaveType->id ? 'selected' : '' }}>{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $value)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Leave Year</label>
                <input type="number" name="leave_year" value="{{ request('leave_year') }}" class="form-control form-control-sm" placeholder="e.g. 2026">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.historical.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Leave Year</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->user?->name ?? '(Deleted)' }}</td>
                            <td>{{ $record->leaveType->name }}</td>
                            <td>
                                {{ $record->start_date->format('d M Y') }}
                                @if (! $record->start_date->equalTo($record->end_date))
                                    &ndash; {{ $record->end_date->format('d M Y') }}
                                @endif
                            </td>
                            <td>{{ $record->total_days }}</td>
                            <td><span class="badge {{ $record->statusBadgeClass() }}">{{ ucfirst($record->status) }}</span></td>
                            <td>{{ $record->leave_year }}</td>
                            <td>{{ $record->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.applications.show', $record) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No historical records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $records->links() }}
    </div>
@endsection

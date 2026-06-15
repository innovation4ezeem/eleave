@extends('layouts.app')

@section('title', 'Historical Leave Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Historical Leave Records</h1>
        <a href="{{ route('admin.historical.create') }}" class="btn btn-primary btn-sm">Add Historical Record</a>
    </div>

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
                            <td>{{ $record->user->name }}</td>
                            <td>{{ $record->leaveType->name }}</td>
                            <td>
                                {{ $record->start_date->format('d M Y') }}
                                @if (! $record->start_date->equalTo($record->end_date))
                                    &ndash; {{ $record->end_date->format('d M Y') }}
                                @endif
                            </td>
                            <td>{{ $record->total_days }}</td>
                            <td><span class="badge {{ $record->statusBadgeClass() }}">{{ ucfirst($record->status) }}</span></td>
                            <td>{{ $record->leave_year }}/{{ $record->leave_year + 1 }}</td>
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

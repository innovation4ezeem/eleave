@extends('layouts.app')

@section('title', 'My Leave Applications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Leave Applications</h1>
        <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">Apply for Leave</a>
    </div>

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

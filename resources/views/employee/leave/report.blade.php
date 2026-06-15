@extends('layouts.app')

@section('title', 'Leave Report')

@push('styles')
    <style>
        @media print {
            .navbar, .no-print, form {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }
        }

        .print-only {
            display: none;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1 class="h3 mb-0">Leave Report</h1>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print Report</button>
    </div>

    <div class="print-only mb-4">
        <h1 class="h4 mb-1">{{ config('app.name') }} &mdash; Leave Report</h1>
        <div>Employee: {{ auth()->user()->name }} ({{ auth()->user()->employee_no ?? 'N/A' }})</div>
        <div>Department: {{ auth()->user()->department?->name ?? '—' }}</div>
        <div>Leave Year: 1 Feb {{ $leaveYear }} &ndash; 31 Jan {{ $leaveYear + 1 }}</div>
    </div>

    <div class="mb-3 no-print">
        <form method="GET" action="{{ route('leave.report') }}" class="row gx-2 gy-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Leave Year</label>
                <div class="btn-group d-flex">
                    <a href="{{ route('leave.report', array_filter(['leave_year' => $leaveYear - 1, 'leave_type_id' => $selectedLeaveTypeId, 'status' => $selectedStatus])) }}" class="btn btn-outline-secondary btn-sm">&laquo; {{ $leaveYear - 1 }}/{{ $leaveYear }}</a>
                    <span class="btn btn-light btn-sm disabled">{{ $leaveYear }}/{{ $leaveYear + 1 }}</span>
                    <a href="{{ route('leave.report', array_filter(['leave_year' => $leaveYear + 1, 'leave_type_id' => $selectedLeaveTypeId, 'status' => $selectedStatus])) }}" class="btn btn-outline-secondary btn-sm">{{ $leaveYear + 1 }}/{{ $leaveYear + 2 }} &raquo;</a>
                </div>
                <input type="hidden" name="leave_year" value="{{ $leaveYear }}">
            </div>

            <div class="col-auto">
                <label for="leave_type_id" class="form-label mb-0">Leave Type</label>
                <select id="leave_type_id" name="leave_type_id" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}" {{ (string) $selectedLeaveTypeId === (string) $leaveType->id ? 'selected' : '' }}>{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <label for="status" class="form-label mb-0">Status</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            </div>
        </form>
        <div class="form-text">Leave year runs 1 Feb {{ $leaveYear }} &ndash; 31 Jan {{ $leaveYear + 1 }}.</div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th>Decided By</th>
                        <th>Remarks</th>
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
                            <td>{{ $application->reason }}</td>
                            <td><span class="badge {{ $application->statusBadgeClass() }}">{{ ucfirst($application->status) }}</span></td>
                            <td>{{ $application->applied_at?->format('d M Y H:i') }}</td>
                            <td>{{ $application->decidedBy?->name ?? '—' }}</td>
                            <td>{{ $application->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No leave records found for this leave year.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Summary (Total Days by Status)</div>
        <div class="card-body">
            @if ($totalDaysByStatus->isEmpty())
                <p class="text-muted mb-0">No leave records found for this leave year.</p>
            @else
                <ul class="mb-0">
                    @foreach ($totalDaysByStatus as $status => $totalDays)
                        <li>{{ ucfirst($status) }}: <strong>{{ number_format((float) $totalDays, 1) }}</strong> day(s)</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection

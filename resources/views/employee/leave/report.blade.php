@extends('layouts.app')

@section('title', 'Leave Report')

@push('styles')
    <style>
        .print-only {
            display: none;
        }

        .report-table th,
        .report-table td {
            white-space: nowrap;
        }

        .report-table .report-truncate {
            display: inline-block;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: bottom;
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            .navbar, .no-print, form {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            body {
                font-size: 11px;
            }

            .report-table th,
            .report-table td {
                white-space: normal;
            }

            .report-table .report-truncate {
                display: block;
                max-width: none;
                overflow: visible;
                white-space: normal;
            }

            /* Cleaner, formal report look: flatten cards, add solid grid lines */
            .card {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .card-header {
                background: none !important;
                border-bottom: 1px solid #000 !important;
                padding: 4px 0 !important;
                font-weight: bold;
            }

            .card-body {
                padding: 0 !important;
            }

            .report-table {
                font-size: 10px;
            }

            .report-table th,
            .report-table td {
                border: 1px solid #000 !important;
                padding: 4px 6px !important;
            }

            .report-table thead th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .table-hover > tbody > tr:hover > * {
                background-color: transparent !important;
            }
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
        @if ($isManagerView)
            <div>Scope: {{ auth()->user()->isHod() ? (auth()->user()->department?->name ?? 'My Department') : 'All Employees' }}</div>
        @else
            <div>Employee: {{ auth()->user()->name }} ({{ auth()->user()->employee_no ?? 'N/A' }})</div>
            <div>Department: {{ auth()->user()->department?->name ?? '—' }}</div>
        @endif
        <div>Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</div>
    </div>

    <div class="mb-3 no-print">
        <form method="GET" action="{{ route('leave.report') }}" class="row gx-2 gy-2 align-items-end">
            <div class="col-auto">
                <label for="date_from" class="form-label mb-0">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>

            <div class="col-auto">
                <label for="date_to" class="form-label mb-0">To</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>

            @if ($isManagerView)
                <div class="col-auto">
                    <label for="user_id" class="form-label mb-0">Employee</label>
                    <select id="user_id" name="user_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ (string) $selectedUserId === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

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
                    @foreach (['pending', 'approved', 'rejected', 'cancelled', 'change_requested'] as $status)
                        <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <a href="{{ route('leave.report') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="report-table table table-sm table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        @if ($isManagerView)
                            <th>Employee</th>
                        @endif
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Applied At</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            @if ($isManagerView)
                                <td>
                                    <span class="report-truncate" title="{{ $application->user?->name ?? '(Deleted)' }}">{{ $application->user?->name ?? '(Deleted)' }}</span>
                                </td>
                            @endif
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
                            <td><span class="badge {{ $application->statusBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</span></td>
                            <td>
                                <span class="report-truncate" title="{{ $application->reason }}">{{ $application->reason }}</span>
                            </td>
                            <td>{{ $application->applied_at?->format('d M Y H:i') }}</td>
                            <td>
                                @if ($application->status === 'pending' || $application->status === 'change_requested')
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="report-truncate" title="{{ $application->decidedBy?->name ?? '—' }}{{ $application->remarks ? ' — '.$application->remarks : '' }}">
                                        {{ $application->decidedBy?->name ?? '—' }}@if ($application->remarks) &mdash; {{ $application->remarks }}@endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isManagerView ? 8 : 7 }}" class="text-center text-muted py-4">No leave records found for the selected filters.</td>
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
                <p class="text-muted mb-0">No leave records found for the selected filters.</p>
            @else
                <ul class="mb-0">
                    @foreach ($totalDaysByStatus as $status => $totalDays)
                        <li>{{ ucfirst(str_replace('_', ' ', $status)) }}: <strong>{{ number_format((float) $totalDays, 1) }}</strong> day(s)</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection

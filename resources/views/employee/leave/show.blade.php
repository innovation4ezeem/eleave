@extends('layouts.app')

@section('title', 'Leave Application Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Application Details</h1>
        <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Back to My Applications</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Leave Type</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->leaveType->name }}</dd>

                        <dt class="col-sm-4">Dates</dt>
                        <dd class="col-sm-8">
                            {{ $leaveApplication->start_date->format('d M Y') }}
                            @if (! $leaveApplication->start_date->equalTo($leaveApplication->end_date))
                                &ndash; {{ $leaveApplication->end_date->format('d M Y') }}
                            @endif
                        </dd>

                        <dt class="col-sm-4">Start Portion</dt>
                        <dd class="col-sm-8">{{ ucfirst($leaveApplication->start_half) }}</dd>

                        <dt class="col-sm-4">End Portion</dt>
                        <dd class="col-sm-8">{{ ucfirst($leaveApplication->end_half) }}</dd>

                        <dt class="col-sm-4">Total Days</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->total_days }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $leaveApplication->statusBadgeClass() }}">{{ ucfirst($leaveApplication->status) }}</span>
                        </dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->reason }}</dd>

                        @if ($leaveApplication->hasDocument())
                            <dt class="col-sm-4">Document</dt>
                            <dd class="col-sm-8">
                                <a href="{{ route('leave.document', $leaveApplication) }}">{{ $leaveApplication->document_original_name }}</a>
                            </dd>
                        @endif

                        @if ($leaveApplication->status !== 'pending')
                            <dt class="col-sm-4">Decided By</dt>
                            <dd class="col-sm-8">{{ $leaveApplication->decidedBy?->name ?? '—' }}</dd>

                            <dt class="col-sm-4">Decided At</dt>
                            <dd class="col-sm-8">{{ $leaveApplication->decided_at?->format('d M Y H:i') ?? '—' }}</dd>

                            @if ($leaveApplication->remarks)
                                <dt class="col-sm-4">Remarks</dt>
                                <dd class="col-sm-8">{{ $leaveApplication->remarks }}</dd>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>

            @if ($leaveApplication->status === 'pending')
                <form method="POST" action="{{ route('leave.cancel', $leaveApplication) }}"
                      onsubmit="return confirm('Are you sure you want to cancel this leave application?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Cancel Application</button>
                </form>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">History</h2>
                    <ul class="list-unstyled mb-0 small">
                        @foreach ($leaveApplication->logs as $log)
                            <li class="mb-2">
                                <div>{{ ucfirst($log->action) }}@if ($log->actor) by {{ $log->actor->name }}@endif</div>
                                <div class="text-muted">{{ $log->created_at->format('d M Y H:i') }}</div>
                                @if ($log->notes)
                                    <div>{{ $log->notes }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

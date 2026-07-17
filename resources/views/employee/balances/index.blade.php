@extends('layouts.app')

@section('title', 'My Leave Balance')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Leave Balance</h1>
    </div>

    <div class="mb-3">
        <div class="btn-group">
            <a href="{{ route('balances.index', ['leave_year' => $leaveYear - 1]) }}" class="btn btn-outline-secondary btn-sm">&laquo; {{ $leaveYear - 1 }}</a>
            <span class="btn btn-light btn-sm disabled">{{ $leaveYear }}</span>
            <a href="{{ route('balances.index', ['leave_year' => $leaveYear + 1]) }}" class="btn btn-outline-secondary btn-sm">{{ $leaveYear + 1 }} &raquo;</a>
        </div>
        <div class="form-text">Leave year runs 1 Jan {{ $leaveYear }} &ndash; 31 Dec {{ $leaveYear }}.</div>
    </div>

    @if (! is_null($carryForwardEstimate))
        <div class="alert alert-info">
            <strong>Estimated Carry Forward Leave: {{ number_format($carryForwardEstimate, 1) }} day(s).</strong>
            This is a live projection for {{ $leaveYear }} based on your unused Annual + Reward Leave so far this year (capped at 5 days).
            It is not final &mdash; it will be confirmed automatically at the start of {{ $leaveYear }} and depends on your remaining balance at year end.
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
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
                    @forelse ($balances as $balance)
                        <tr>
                            <td>{{ $balance->leaveType->name }}</td>
                            @if (! is_null($carryForwardEstimate) && $balance->leaveType->code === 'carry_forward')
                                <td class="text-end">{{ number_format($carryForwardEstimate, 1) }} <span class="badge bg-info text-dark">est.</span></td>
                                <td class="text-end">{{ $balance->adjustment_days }}</td>
                                <td class="text-end">{{ $balance->used_days }}</td>
                                <td class="text-end"><strong>{{ number_format($carryForwardEstimate + (float) $balance->adjustment_days - (float) $balance->used_days, 1) }}</strong></td>
                            @else
                                <td class="text-end">{{ $balance->entitlement_days }}</td>
                                <td class="text-end">{{ $balance->adjustment_days }}</td>
                                <td class="text-end">{{ $balance->used_days }}</td>
                                <td class="text-end"><strong>{{ $balance->remaining_days }}</strong></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No leave balances found for this leave year.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', "Year-end Rollover {$leaveYear}")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Year-end Rollover into {{ $leaveYear }}</h1>
        <a href="{{ route('admin.leave-years.index') }}" class="btn btn-outline-secondary btn-sm">Back to Leave Years</a>
    </div>

    <p class="text-muted">
        Preview only &mdash; nothing has changed yet. Running the rollover sets each employee's
        {{ $leaveYear }} Carry Forward Leave to their unused {{ $previousYear }} Annual + Reward Leave
        (capped at {{ number_format(\App\Services\LeaveYearRolloverService::CARRY_FORWARD_CAP, 0) }} days),
        creates any missing {{ $leaveYear }} balances, and locks (archives) leave year {{ $previousYear }}.
        It is safe to run more than once; every Carry Forward that changes on a re-run is logged in the balance history.
    </p>

    @if ($blockedReason)
        <div class="alert alert-danger">{{ $blockedReason }}</div>
    @endif

    @if ($undecided->isNotEmpty())
        <div class="alert alert-warning">
            <strong>{{ $undecided->count() }} application(s) from {{ $previousYear }} are still undecided.</strong>
            Once {{ $previousYear }} is locked they can no longer be approved without unlocking it again,
            so it is best to decide them first.
            <ul class="mb-0 mt-2">
                @foreach ($undecided as $application)
                    <li>
                        <a href="{{ route('admin.applications.show', $application) }}">{{ $application->user?->name }}</a>
                        &mdash; {{ $application->leaveType?->name }},
                        {{ $application->start_date->format('d M Y') }}
                        ({{ $application->total_days }} day(s), {{ str_replace('_', ' ', $application->status) }})
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $changed = $rows->filter(fn ($row) => $row['current'] === null || $row['current'] !== $row['new']);
    @endphp

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ $rows->count() }} active employee(s) &middot; {{ $changed->count() }} Carry Forward value(s) will be set or changed</span>
            @if ($previousLocked)
                <span class="badge bg-secondary">{{ $previousYear }} already locked &mdash; this is a re-run</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Unused {{ $previousYear }} Annual</th>
                        <th class="text-end">Unused {{ $previousYear }} Reward</th>
                        <th class="text-end">Carry Forward now</th>
                        <th class="text-end">Carry Forward after</th>
                        <th class="text-end">CF used so far</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $isChange = $row['current'] !== null && $row['current'] !== $row['new'];
                            $overUsed = $row['used'] > $row['new'];
                        @endphp
                        <tr class="{{ $overUsed ? 'table-danger' : ($isChange ? 'table-warning' : '') }}">
                            <td>
                                {{ $row['user']->name }}
                                @if ($row['user']->employee_no)
                                    <span class="text-muted small">({{ $row['user']->employee_no }})</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($row['annual'], 1) }}</td>
                            <td class="text-end">{{ number_format($row['reward'], 1) }}</td>
                            <td class="text-end">{{ $row['current'] === null ? '—' : number_format($row['current'], 1) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($row['new'], 1) }}</td>
                            <td class="text-end">
                                {{ number_format($row['used'], 1) }}
                                @if ($overUsed)
                                    <div class="small text-danger">more than the new Carry Forward</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No active employees.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @unless ($blockedReason)
        <form method="POST" action="{{ route('admin.leave-years.rollover', $leaveYear) }}"
              onsubmit="return confirm('Run the year-end rollover into {{ $leaveYear }} and lock {{ $previousYear }}?');">
            @csrf
            <button type="submit" class="btn btn-warning">Run rollover &amp; lock {{ $previousYear }}</button>
        </form>
    @endunless
@endsection

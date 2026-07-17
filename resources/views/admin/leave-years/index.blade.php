@extends('layouts.app')

@section('title', 'Leave Years')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Years</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <p class="text-muted">
        Locking (archiving) a leave year makes its records read-only: employees can no longer cancel or request
        changes to that year's leave, and balances for that year cannot be edited. Data stays fully viewable.
        The previous leave year is locked automatically at year-end rollover. The Historical Leave tool stays
        available so admins can still backfill or correct archived years.
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Leave Year</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        @php $isLocked = $lockedYears->has($year); @endphp
                        <tr>
                            <td>
                                {{ $year }}
                                @if ($year === $current)
                                    <span class="badge bg-primary">Current</span>
                                @endif
                            </td>
                            <td>1 Jan {{ $year }} &ndash; 31 Dec {{ $year }}</td>
                            <td>
                                @if ($isLocked)
                                    <span class="badge bg-secondary">Locked (archived)</span>
                                @else
                                    <span class="badge bg-success">Open</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($isLocked)
                                    <form method="POST" action="{{ route('admin.leave-years.unlock', $year) }}" class="d-inline" onsubmit="return confirm('Unlock this leave year? Its records become editable again.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Unlock</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.leave-years.lock', $year) }}" class="d-inline" onsubmit="return confirm('Lock (archive) this leave year? Its records become read-only.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Lock</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No leave years found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

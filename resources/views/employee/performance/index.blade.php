@extends('layouts.app')

@section('title', 'Performance')

@section('content')
    <h1 class="h3 mb-4">{{ $isManagerView ? 'Performance Scores' : 'My Performance' }}</h1>

    @if ($isManagerView)
        <form method="GET" action="{{ route('performance.index') }}" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="user_id" class="form-label mb-0">Employee</label>
                    <select id="user_id" name="user_id" class="form-select form-select-sm">
                        <option value="">All (me + my department)</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ (string) $selectedUserId === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('performance.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>
    @endif

    <div class="card mb-4">
        <div class="card-header">Yearly Summary <span class="text-muted small">(average is over months with a recorded score, not ÷ 12)</span></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        @if ($isManagerView)
                            <th>Employee</th>
                        @endif
                        <th>Year</th>
                        <th class="text-end">Months Recorded</th>
                        <th class="text-end">Total Score</th>
                        <th class="text-end">Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        <tr>
                            @if ($isManagerView)
                                <td>{{ $names[$summary->user_id] ?? '(Deleted)' }}</td>
                            @endif
                            <td>{{ $summary->year }}</td>
                            <td class="text-end">{{ $summary->months }}</td>
                            <td class="text-end">{{ number_format((float) $summary->total, 2) }}</td>
                            <td class="text-end"><strong>{{ number_format((float) $summary->average, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isManagerView ? 5 : 4 }}" class="text-center text-muted py-3">No yearly totals yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Monthly Scores</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Period</th>
                        @if ($isManagerView)
                            <th>Employee</th>
                        @endif
                        <th>Score Breakdown</th>
                        <th class="text-end">Total Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->periodLabel() }}</td>
                            @if ($isManagerView)
                                <td>{{ $entry->user?->name ?? '(Deleted)' }}</td>
                            @endif
                            <td>
                                @foreach ($entry->scores as $name => $score)
                                    <span class="badge bg-light text-dark border me-1">{{ $name }}: {{ is_null($score) ? '—' : number_format((float) $score, 2) }}</span>
                                @endforeach
                            </td>
                            <td class="text-end">
                                <strong>{{ number_format((float) $entry->total_score, 2) }}</strong>
                                @if (collect($entry->scores)->contains(null))
                                    <span class="badge bg-warning text-dark d-block mt-1">Partial</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isManagerView ? 4 : 3 }}" class="text-center text-muted py-4">No performance scores recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $entries->links() }}
    </div>
@endsection

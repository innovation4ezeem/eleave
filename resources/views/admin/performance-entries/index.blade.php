@extends('layouts.app')

@section('title', 'Performance Entries')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Performance Entries</h1>
        <div>
            <a href="{{ route('admin.performance-settings.index') }}" class="btn btn-outline-secondary btn-sm">Scoring Formula</a>
            <a href="{{ route('admin.performance-entries.create') }}" class="btn btn-primary btn-sm">Add Entry</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.performance-entries.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-0">Employee</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" {{ (string) request('user_id') === (string) $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0">Year</label>
                <input type="number" name="year" value="{{ request('year') }}" class="form-control form-control-sm" placeholder="e.g. 2026">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ (string) request('month') === (string) $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.performance-entries.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Employee</th>
                        <th>Score Breakdown</th>
                        <th class="text-end">Total</th>
                        <th>Entered By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->periodLabel() }}</td>
                            <td>{{ $entry->user?->name ?? '(Deleted)' }}</td>
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
                            <td class="small text-muted">{{ $entry->enteredBy?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.performance-entries.edit', $entry) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.performance-entries.destroy', $entry) }}" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No performance entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $entries->links() }}
    </div>
@endsection

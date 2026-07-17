@extends('layouts.app')

@section('title', 'Competence Voting Log')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Competence Voting Log</h1>
        <a href="{{ route('admin.competence-categories.index') }}" class="btn btn-outline-secondary btn-sm">Manage Categories</a>
    </div>

    <form method="GET" action="{{ route('admin.competence-votes.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-0">Voter</label>
                <select name="voter_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" {{ (string) request('voter_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-0">Votee</label>
                <select name="votee_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" {{ (string) request('votee_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" {{ (string) request('category_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.competence-votes.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    @if ($totalsByCategory->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">Totals for the filtered range</div>
            <div class="card-body">
                @foreach ($categories as $c)
                    @if (isset($totalsByCategory[$c->id]))
                        <span class="badge bg-secondary me-2 mb-1">{{ $c->name }}: {{ $totalsByCategory[$c->id] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Voter</th>
                        <th>Voted For</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($votes as $vote)
                        <tr>
                            <td>{{ $vote->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $vote->voter?->name ?? '(Deleted)' }}</td>
                            <td>{{ $vote->votee?->name ?? '(Deleted)' }}</td>
                            <td>{{ $vote->category?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No votes match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $votes->links() }}
    </div>
@endsection

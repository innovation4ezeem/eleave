@extends('layouts.app')

@section('title', 'Competence Results')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Competence Results</h1>
        <a href="{{ route('admin.competence-votes.index') }}" class="btn btn-outline-secondary btn-sm">Detailed Voting Log</a>
    </div>

    <p class="text-muted">Votes received by each person, broken down by category, within the selected date range. Leave the dates blank for all-time totals.</p>

    <form method="GET" action="{{ route('admin.competence-results.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="date_from" class="form-label mb-0">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label for="date_to" class="form-label mb-0">To</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.competence-results.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="position: sticky; left: 0;" class="bg-light">Employee</th>
                        @foreach ($categories as $category)
                            <th class="text-end">{{ $category->name }}</th>
                        @endforeach
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td style="position: sticky; left: 0;" class="bg-white">
                                {{ $user->name }}
                                <span class="badge bg-secondary text-uppercase ms-1" style="font-size: .65rem;">{{ $user->role }}</span>
                            </td>
                            @foreach ($categories as $category)
                                @php $count = $matrix[$user->id][$category->id] ?? 0; @endphp
                                <td class="text-end {{ $count === 0 ? 'text-muted' : '' }}">{{ $count }}</td>
                            @endforeach
                            <td class="text-end"><strong>{{ $userTotals[$user->id] ?? 0 }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $categories->count() + 2 }}" class="text-center text-muted py-4">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="alert alert-light border mt-3">No competence categories configured yet.</div>
    @endif
@endsection

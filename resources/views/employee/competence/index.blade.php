@extends('layouts.app')

@section('title', 'Personal Competence')

@section('content')
    <h1 class="h3 mb-4">Personal Competence</h1>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Vote a Colleague</h2>
                    <p class="small text-muted">Recognise a colleague in a category. There's no limit on how many times you can vote. Votes are anonymous &mdash; the recipient only sees totals, never who voted.</p>

                    <form method="POST" action="{{ route('competence.vote') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="votee_id" class="form-label">Colleague</label>
                            <select id="votee_id" name="votee_id" class="form-select @error('votee_id') is-invalid @enderror" required>
                                <option value="">-- Select colleague --</option>
                                @foreach ($colleagues as $colleague)
                                    <option value="{{ $colleague->id }}" {{ (string) old('votee_id') === (string) $colleague->id ? 'selected' : '' }}>{{ $colleague->name }}</option>
                                @endforeach
                            </select>
                            @error('votee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                <option value="">-- Select category --</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($categories->isEmpty())
                            <div class="alert alert-light border small mb-3">No voting categories are available yet.</div>
                        @endif

                        <button type="submit" class="btn btn-primary" {{ $categories->isEmpty() || $colleagues->isEmpty() ? 'disabled' : '' }}>Submit Vote</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0">Votes I Received</h2>
                    </div>

                    <form method="GET" action="{{ route('competence.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label for="date_from" class="form-label mb-0 small">From</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label for="date_to" class="form-label mb-0 small">To</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                            <a href="{{ route('competence.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>

                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Votes Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr>
                                    <td>{{ $category->name }}</td>
                                    <td class="text-end"><strong>{{ $totalsByCategory[$category->id] ?? 0 }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted text-center py-3">No categories configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

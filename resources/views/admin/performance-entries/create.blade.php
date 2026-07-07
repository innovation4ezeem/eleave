@extends('layouts.app')

@section('title', $entry ? 'Edit Performance Entry' : 'Add Performance Entry')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ $entry ? 'Edit Performance Entry' : 'Add Performance Entry' }}</h1>
        <a href="{{ route('admin.performance-entries.index') }}" class="btn btn-outline-secondary btn-sm">Back to Entries</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $entry ? route('admin.performance-entries.update', $entry) : route('admin.performance-entries.store') }}">
                @csrf
                @if ($entry)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="user_id" class="form-label">Employee</label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">-- Select --</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" {{ (string) old('user_id', $entry?->user_id) === (string) $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="year" class="form-label">Year</label>
                        <input type="number" id="year" name="year" min="2000" max="2100" value="{{ old('year', $entry?->year ?? now()->year) }}" class="form-control @error('year') is-invalid @enderror" required>
                        @error('year')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="month" class="form-label">Month</label>
                        <select id="month" name="month" class="form-select @error('month') is-invalid @enderror" required>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ (string) old('month', $entry?->month ?? now()->month) === (string) $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                            @endfor
                        </select>
                        @error('month')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr>
                <h2 class="h6 mb-3">Input Values</h2>

                @if ($inputs->isEmpty())
                    <div class="alert alert-warning">No input fields are configured. Add them under <a href="{{ route('admin.performance-settings.index') }}">Scoring Formula</a> first.</div>
                @else
                    <div class="row">
                        @foreach ($inputs as $input)
                            <div class="col-md-4 mb-3">
                                <label for="in_{{ $input->key }}" class="form-label">{{ $input->label }} <code class="small text-muted">{{ $input->key }}</code></label>
                                <input type="number" step="any" min="0" id="in_{{ $input->key }}" name="values[{{ $input->key }}]"
                                       value="{{ old('values.'.$input->key, $entry?->values[$input->key] ?? '') }}"
                                       class="form-control @error('values.'.$input->key) is-invalid @enderror">
                                @error('values.'.$input->key)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="form-text mb-3">You can leave some fields blank and fill them in later &mdash; only components whose inputs are complete will be scored, and the total updates each time you save.</div>

                    <button type="submit" class="btn btn-primary">{{ $entry ? 'Update & Recalculate' : 'Save & Calculate Score' }}</button>
                @endif
            </form>
        </div>
    </div>

    @if ($entry)
        @php $isPartial = collect($entry->scores)->contains(null); @endphp
        <div class="card mt-3">
            <div class="card-header">
                Current Computed Score &mdash; {{ $entry->periodLabel() }}
                @if ($isPartial)
                    <span class="badge bg-warning text-dark ms-1">Partial</span>
                @endif
            </div>
            <div class="card-body">
                <ul class="mb-2">
                    @foreach ($entry->scores as $name => $score)
                        <li>{{ $name }}: <strong>{{ is_null($score) ? '— (pending)' : number_format((float) $score, 2) }}</strong></li>
                    @endforeach
                </ul>
                <div class="h5 mb-0">Total{{ $isPartial ? ' so far' : '' }}: {{ number_format((float) $entry->total_score, 2) }}</div>
            </div>
        </div>
    @endif
@endsection

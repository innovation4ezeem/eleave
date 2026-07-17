@extends('layouts.app')

@section('title', 'Performance Scoring Formula')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Performance Scoring Formula</h1>
        <a href="{{ route('admin.performance-entries.index') }}" class="btn btn-outline-secondary btn-sm">Performance Entries</a>
    </div>

    <p class="text-muted">
        Define the <strong>input fields</strong> admin fills in for each employee, and the <strong>score components</strong> computed from them.
        Each component is an arithmetic formula using input keys, e.g. <code>(achieved / objective) * 50</code>.
        Allowed operators: <code>+ - * / ( )</code>. A component's total is the sum of all active components.
        <br>Available variable keys: <strong>{{ $inputs->pluck('key')->implode(', ') ?: '(none yet)' }}</strong>.
    </p>

    <div class="row">
        {{-- Inputs --}}
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header">Input Fields</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.performance-settings.inputs.store') }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-md-5">
                            <label for="key" class="form-label">Key (formula variable)</label>
                            <input type="text" id="key" name="key" value="{{ old('key') }}" class="form-control form-control-sm @error('key') is-invalid @enderror" placeholder="e.g. attendance" required>
                            @error('key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5">
                            <label for="label" class="form-label">Label</label>
                            <input type="text" id="label" name="label" value="{{ old('label') }}" class="form-control form-control-sm @error('label') is-invalid @enderror" placeholder="e.g. Attendance" required>
                            @error('label')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
                        </div>
                    </form>

                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Key</th><th>Label</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($inputs as $input)
                                <tr>
                                    <td><code>{{ $input->key }}</code></td>
                                    <td>{{ $input->label }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.performance-settings.inputs.destroy', $input) }}" class="d-inline" onsubmit="return confirm('Remove this input field?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">No input fields yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Components --}}
        <div class="col-lg-7 mb-4">
            <div class="card">
                <div class="card-header">Score Components</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.performance-settings.components.store') }}" class="mb-4">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label for="c_name" class="form-label">Name</label>
                                <input type="text" id="c_name" name="name" value="{{ old('name') }}" class="form-control form-control-sm @error('name') is-invalid @enderror" placeholder="e.g. KPI (50%)" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="c_expr" class="form-label">Formula</label>
                                <input type="text" id="c_expr" name="expression" value="{{ old('expression') }}" class="form-control form-control-sm @error('expression') is-invalid @enderror" placeholder="(achieved / objective) * 50" required>
                                @error('expression')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
                            </div>
                        </div>
                    </form>

                    @forelse ($components as $component)
                        <form method="POST" action="{{ route('admin.performance-settings.components.update', $component) }}" class="border rounded p-2 mb-2">
                            @csrf
                            @method('PUT')
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4">
                                    <input type="text" name="name" value="{{ $component->name }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-5">
                                    <input type="text" name="expression" value="{{ $component->expression }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-3 text-end">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active-{{ $component->id }}" {{ $component->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="active-{{ $component->id }}">Active</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                            </div>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.performance-settings.components.destroy', $component) }}" onsubmit="return confirm('Remove this component?');" class="mb-3 text-end">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">Remove component</button>
                        </form>
                    @empty
                        <p class="text-muted text-center py-3 mb-0">No score components yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

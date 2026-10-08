@extends('layouts.app')

@section('content')
<div class="container py-4">

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h1 class="h3 mb-4">Subdivisions</h1>

    <div class="row g-4">
        {{-- Left: existing subdivisions --}}
        <div class="col-md-8">

            {{-- Standalone (no department) --}}
            @php
                $standalone = \App\Models\Subdivision::whereNull('department_id')->orderBy('name')->get();
            @endphp
            @if ($standalone->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header fw-semibold">General / Cross-Department</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($standalone as $sub)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <form method="POST" action="{{ route('admin.subdivisions.update', $sub) }}" class="d-flex gap-2 flex-grow-1 me-2">
                                    @csrf @method('PATCH')
                                    <input type="text" name="name" value="{{ $sub->name }}" class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-outline-primary">Rename</button>
                                </form>
                                <form method="POST" action="{{ route('admin.subdivisions.destroy', $sub) }}"
                                      onsubmit="return confirm('Delete {{ addslashes($sub->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Per-department --}}
            @foreach ($departments as $dept)
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ $dept->name }}</div>
                    @if ($dept->subdivisions->isEmpty())
                        <div class="card-body text-muted">No subdivisions yet.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($dept->subdivisions as $sub)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <form method="POST" action="{{ route('admin.subdivisions.update', $sub) }}" class="d-flex gap-2 flex-grow-1 me-2">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $sub->name }}" class="form-control form-control-sm">
                                        <button class="btn btn-sm btn-outline-primary">Rename</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.subdivisions.destroy', $sub) }}"
                                          onsubmit="return confirm('Delete {{ addslashes($sub->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Right: Add form --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header fw-semibold">Add Subdivision</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.subdivisions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Department <span class="text-muted fw-normal">(optional)</span></label>
                            <select name="department_id" class="form-select">
                                <option value="">— none (cross-department) —</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subdivision Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g. AI Innovation">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

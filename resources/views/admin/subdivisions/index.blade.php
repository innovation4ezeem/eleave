@extends('layouts.app')

@section('title', 'Subdivisions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Subdivisions</h1>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        {{-- Left: existing subdivisions grouped by department --}}
        <div class="col-lg-8">
            @forelse ($departments as $department)
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ $department->name }}</div>
                    <div class="card-body p-0">
                        @if ($department->subdivisions->isEmpty())
                            <p class="text-muted small px-3 py-2 mb-0">No subdivisions yet.</p>
                        @else
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @foreach ($department->subdivisions as $sub)
                                        <tr>
                                            <td class="ps-3">{{ $sub->name }}</td>
                                            <td class="text-end pe-3">
                                                {{-- inline rename --}}
                                                <form method="POST"
                                                      action="{{ route('admin.subdivisions.update', $sub) }}"
                                                      class="d-inline-flex gap-1 align-items-center">
                                                    @csrf @method('PUT')
                                                    <input type="text" name="name"
                                                           value="{{ $sub->name }}"
                                                           class="form-control form-control-sm"
                                                           style="width:160px">
                                                    <button class="btn btn-sm btn-outline-secondary">Rename</button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('admin.subdivisions.destroy', $sub) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Delete this subdivision?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-muted">No active departments found.</p>
            @endforelse
        </div>

        {{-- Right: add new subdivision --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Add Subdivision</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.subdivisions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">— select —</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subdivision Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g. Team Alpha" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

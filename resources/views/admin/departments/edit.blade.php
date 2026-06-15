@extends('layouts.app')

@section('title', 'Edit Department')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Department</h1>
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary btn-sm">Back to Departments</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.departments.update', $department) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $department->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="code" class="form-label">Code (optional)</label>
                    <input type="text" id="code" name="code" value="{{ old('code', $department->code) }}" class="form-control @error('code') is-invalid @enderror">
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>

                <div class="mb-3">
                    <label for="approver_ids" class="form-label">Approvers</label>
                    @php
                        $selectedApprovers = old('approver_ids', $department->approvers->pluck('id')->all());
                    @endphp
                    <select id="approver_ids" name="approver_ids[]" class="form-select @error('approver_ids') is-invalid @enderror" multiple size="6">
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" {{ collect($selectedApprovers)->contains($admin->id) ? 'selected' : '' }}>{{ $admin->name }} ({{ $admin->email }})</option>
                        @endforeach
                    </select>
                    <div class="form-text">Admins selected here can approve/reject leave for employees in this department. If none are selected, any admin may act.</div>
                    @error('approver_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
@endsection

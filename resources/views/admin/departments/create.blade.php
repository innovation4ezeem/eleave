@extends('layouts.app')

@section('title', 'Add Department')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Add Department</h1>
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary btn-sm">Back to Departments</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.departments.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="code" class="form-label">Code (optional)</label>
                    <input type="text" id="code" name="code" value="{{ old('code') }}" class="form-control @error('code') is-invalid @enderror">
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>

                <button type="submit" class="btn btn-primary">Create Department</button>
            </form>
        </div>
    </div>
@endsection

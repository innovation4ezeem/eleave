@extends('layouts.app')

@section('title', 'Competence Categories')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Competence Categories</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <p class="text-muted">Categories that HODs and employees vote on (e.g. Accountability, Innovative &amp; Creativity). Deactivating a category hides it from new voting but keeps existing votes in the log.</p>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Add a Category</h2>
            <form method="POST" action="{{ route('admin.competence-categories.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Status</th>
                        <th class="text-end">Total Votes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>
                                <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="text-end">{{ $category->votes_count }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.competence-categories.toggle', $category) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.competence-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Remove this category? Its votes will also be deleted.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No categories yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

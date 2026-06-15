@extends('layouts.app')

@section('title', 'Departments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Departments</h1>
        <a href="{{ route('admin.departments.create') }}" class="btn btn-primary btn-sm">Add Department</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Employees</th>
                        <th>Approvers</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>{{ $department->name }}</td>
                            <td>{{ $department->code ?? '—' }}</td>
                            <td>{{ $department->users_count }}</td>
                            <td>
                                {{ $department->approvers_count }}
                                @if ($department->approvers_count === 0)
                                    <span class="badge bg-warning text-dark">No approver set</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $department->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $department->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="d-inline" onsubmit="return confirm('Delete this department?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No departments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

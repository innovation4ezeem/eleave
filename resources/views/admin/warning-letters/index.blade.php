@extends('layouts.app')

@section('title', 'Warning Letters')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Warning Letters</h1>
        <a href="{{ route('admin.warning-letters.create') }}" class="btn btn-primary btn-sm">Upload Warning Letter</a>
    </div>

    <form method="GET" action="{{ route('admin.warning-letters.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Employee</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ (string) request('user_id') === (string) $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}@if ($employee->employee_no) ({{ $employee->employee_no }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.warning-letters.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date Issued</th>
                        <th>Employee</th>
                        <th>Level</th>
                        <th>File</th>
                        <th>Uploaded By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($letters as $letter)
                        <tr>
                            <td>{{ $letter->issued_date->format('d M Y') }}</td>
                            <td>{{ $letter->employee?->name ?? '(Deleted)' }}</td>
                            <td>{{ $letter->level }}</td>
                            <td><a href="{{ route('warning-letters.download', $letter) }}">{{ $letter->original_filename }}</a></td>
                            <td>{{ $letter->uploader?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.warning-letters.edit', $letter) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.warning-letters.destroy', $letter) }}" class="d-inline" onsubmit="return confirm('Delete this warning letter?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No warning letters found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $letters->links() }}
    </div>
@endsection

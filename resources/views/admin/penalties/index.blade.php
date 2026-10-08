@extends('layouts.app')

@section('title', 'Penalties & Warnings')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Penalties &amp; Warnings</h1>
        <a href="{{ route('admin.penalties.create') }}" class="btn btn-primary btn-sm">Add Penalty</a>
    </div>

    <form method="GET" action="{{ route('admin.penalties.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Employee name or no.">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="penalty_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="warning" {{ request('penalty_type') === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="cash" {{ request('penalty_type') === 'cash' ? 'selected' : '' }}>Cash</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Level</label>
                <select name="penalty_level" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ([1, 2, 3] as $level)
                        <option value="{{ $level }}" {{ (string) request('penalty_level') === (string) $level ? 'selected' : '' }}>Level {{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cash Status</label>
                <select name="collection_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" {{ request('collection_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="collected" {{ request('collection_status') === 'collected' ? 'selected' : '' }}>Collected</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.penalties.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Target</th>
                        <th>Type</th>
                        <th>Level</th>
                        <th>Charges</th>
                        <th>Cash Status</th>
                        <th>Reported By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penalties as $penalty)
                        <tr>
                            <td>{{ $penalty->penalty_date->format('d M Y') }}</td>
                            <td>
                                @if ($penalty->user_id)
                                    {{ $penalty->user?->name ?? '(Deleted)' }}
                                @else
                                    <span class="text-muted small">Dept:</span> {{ $penalty->department?->name ?? '—' }}
                                    @if ($penalty->subdivision)
                                        <span class="text-muted small">/ {{ $penalty->subdivision->name }}</span>
                                    @endif
                                @endif
                            </td>
                            <td><span class="badge {{ $penalty->typeBadgeClass() }}">{{ ucfirst($penalty->penalty_type) }}</span></td>
                            <td>{{ $penalty->levelLabel() }}</td>
                            <td>{{ $penalty->isCash() ? 'RM '.number_format((float) $penalty->penalty_charges, 2) : '—' }}</td>
                            <td>
                                @if ($penalty->isCash())
                                    <span class="badge {{ $penalty->statusBadgeClass() }}">{{ ucfirst($penalty->collection_status) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $penalty->reported_by }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.penalties.show', $penalty) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('admin.penalties.edit', $penalty) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.penalties.destroy', $penalty) }}" class="d-inline" onsubmit="return confirm('Delete this penalty?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No penalties found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $penalties->links() }}
    </div>
@endsection

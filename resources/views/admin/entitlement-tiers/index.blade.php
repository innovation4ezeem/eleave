@extends('layouts.app')

@section('title', 'Leave Entitlement Tiers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Entitlement Tiers</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <p class="text-muted">
        Define how many days of a leave type an employee is entitled to based on their completed years of service
        (measured from their Joined Date at the start of each leave year). When a leave type has tiers, new balances
        use the matching tier instead of the flat default quota. Leave types without any tiers keep using their default quota.
    </p>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Add a Tier</h2>
            <form method="POST" action="{{ route('admin.entitlement-tiers.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label for="leave_type_id" class="form-label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                        <option value="">-- Select --</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ (string) old('leave_type_id') === (string) $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="min_years" class="form-label">Min. Years of Service</label>
                    <input type="number" id="min_years" name="min_years" min="0" max="60" step="1" value="{{ old('min_years') }}" class="form-control @error('min_years') is-invalid @enderror" required>
                    @error('min_years')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="days" class="form-label">Entitlement (days)</label>
                    <input type="number" id="days" name="days" min="0" max="365" step="0.5" value="{{ old('days') }}" class="form-control @error('days') is-invalid @enderror" required>
                    @error('days')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Add Tier</button>
                </div>
            </form>
            <div class="form-text mt-2">Example for Annual Leave: 0 years &rarr; 14 days, 5 years &rarr; 18 days. An employee with 6 years of service would receive 18.</div>
        </div>
    </div>

    @foreach ($leaveTypes as $type)
        @if ($type->entitlementTiers->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">{{ $type->name }} <span class="text-muted small">(default quota: {{ $type->default_annual_quota }} days)</span></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>From (completed years of service)</th>
                                <th>Entitlement (days)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($type->entitlementTiers as $tier)
                                <tr>
                                    <td>{{ $tier->min_years }}+ year(s)</td>
                                    <td>{{ $tier->days }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.entitlement-tiers.destroy', $tier) }}" class="d-inline" onsubmit="return confirm('Remove this tier?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

    @if ($leaveTypes->every(fn ($t) => $t->entitlementTiers->isEmpty()))
        <div class="alert alert-light border">No entitlement tiers configured. All leave types currently use their flat default quota.</div>
    @endif
@endsection

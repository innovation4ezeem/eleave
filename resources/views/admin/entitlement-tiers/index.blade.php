@extends('layouts.app')

@section('title', 'Leave Entitlement Tiers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Entitlement Tiers</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <p class="text-muted">
        Define how many days of a leave type an employee is entitled to based on their completed years of service
        (measured from their Joined Date at the start of each leave year) and/or employee status. When a leave type has tiers, new balances
        use the matching tier instead of the flat default quota. Leave types without any tiers keep using their default quota.
        <br>
        <strong>Employee Status:</strong> Leave blank to apply to all statuses, or select a specific status (Probation, Intern, Permanent).
    </p>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Add a Tier</h2>
            <form method="POST" action="{{ route('admin.entitlement-tiers.store') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="leave_type_id" class="form-label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                        <option value="">-- Select --</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ (string) old('leave_type_id') === (string) $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="min_years" class="form-label">Min. Years of Service</label>
                        <input type="number" id="min_years" name="min_years" min="0" max="60" step="1" value="{{ old('min_years') }}" class="form-control @error('min_years') is-invalid @enderror" required>
                        @error('min_years')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="days" class="form-label">Entitlement (days)</label>
                        <input type="number" id="days" name="days" min="0" max="365" step="0.5" value="{{ old('days') }}" class="form-control @error('days') is-invalid @enderror" required>
                        @error('days')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="employee_status" class="form-label">Applies To</label>
                        <select id="employee_status" name="employee_status" class="form-select @error('employee_status') is-invalid @enderror">
                            <option value="">All Employees</option>
                            <option value="probation" {{ old('employee_status') === 'probation' ? 'selected' : '' }}>Probation Only</option>
                            <option value="intern" {{ old('employee_status') === 'intern' ? 'selected' : '' }}>Intern Only</option>
                            <option value="permanent" {{ old('employee_status') === 'permanent' ? 'selected' : '' }}>Permanent Only</option>
                        </select>
                        @error('employee_status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Add Tier</button>
                    </div>
                </div>
            </form>
            <div class="form-text">Example for Annual Leave: 0 years &rarr; 14 days, 5 years &rarr; 18 days. An employee with 6 years of service would receive 18.</div>
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
                                <th>Employee Status</th>
                                <th>Entitlement (days)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($type->entitlementTiers as $tier)
                                <tr>
                                    <td>{{ $tier->min_years }}+ year(s)</td>
                                    <td>
                                        @if ($tier->employee_status)
                                            <span class="badge bg-info">{{ ucfirst($tier->employee_status) }}</span>
                                        @else
                                            <span class="badge bg-secondary">All Employees</span>
                                        @endif
                                    </td>
                                    <td>{{ $tier->days }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.entitlement-tiers.edit', $tier) }}" class="btn btn-sm btn-outline-primary">Edit</a>
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

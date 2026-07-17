@extends('layouts.app')

@section('title', 'Edit Entitlement Tier')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Entitlement Tier</h1>
        <a href="{{ route('admin.entitlement-tiers.index') }}" class="btn btn-outline-secondary btn-sm">Back to Tiers</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.entitlement-tiers.update', $entitlementTier) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="leave_type_id" class="form-label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required disabled>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ old('leave_type_id', $entitlementTier->leave_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Leave type cannot be changed — remove and re-add if needed.</small>
                    <input type="hidden" name="leave_type_id" value="{{ $entitlementTier->leave_type_id }}">
                    @error('leave_type_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="min_years" class="form-label">Min. Years of Service</label>
                    <input type="number" id="min_years" name="min_years" min="0" max="60" step="1" value="{{ old('min_years', $entitlementTier->min_years) }}" class="form-control @error('min_years') is-invalid @enderror" required disabled>
                    <small class="form-text text-muted">Years of service cannot be changed — remove and re-add if needed.</small>
                    <input type="hidden" name="min_years" value="{{ $entitlementTier->min_years }}">
                    @error('min_years')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="days" class="form-label">Entitlement (days)</label>
                    <input type="number" id="days" name="days" min="0" max="365" step="0.5" value="{{ old('days', $entitlementTier->days) }}" class="form-control @error('days') is-invalid @enderror" required>
                    @error('days')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="employee_status" class="form-label">Employee Status</label>
                    <select id="employee_status" name="employee_status" class="form-select @error('employee_status') is-invalid @enderror">
                        <option value="">-- All Statuses --</option>
                        <option value="probation" {{ old('employee_status', $entitlementTier->employee_status) === 'probation' ? 'selected' : '' }}>Probation</option>
                        <option value="intern" {{ old('employee_status', $entitlementTier->employee_status) === 'intern' ? 'selected' : '' }}>Intern</option>
                        <option value="permanent" {{ old('employee_status', $entitlementTier->employee_status) === 'permanent' ? 'selected' : '' }}>Permanent</option>
                    </select>
                    @error('employee_status')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.entitlement-tiers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

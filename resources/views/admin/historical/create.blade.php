@extends('layouts.app')

@section('title', 'Add Historical Leave Record')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Add Historical Leave Record</h1>
        <a href="{{ route('admin.historical.index') }}" class="btn btn-outline-secondary btn-sm">View Historical Records</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.historical.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="user_id" class="form-label">Employee</label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">-- Select employee --</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (string) old('user_id') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }} ({{ $employee->email }})</option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="leave_type_id" class="form-label">Leave Type</label>
                        <select id="leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                            <option value="">-- Select leave type --</option>
                            @foreach ($leaveTypes as $leaveType)
                                <option value="{{ $leaveType->id }}" {{ (string) old('leave_type_id') === (string) $leaveType->id ? 'selected' : '' }}>{{ $leaveType->name }}</option>
                            @endforeach
                        </select>
                        @error('leave_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="text" id="start_date" name="start_date" value="{{ old('start_date') }}" class="form-control @error('start_date') is-invalid @enderror" autocomplete="off" required>
                        @error('start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="text" id="end_date" name="end_date" value="{{ old('end_date') }}" class="form-control @error('end_date') is-invalid @enderror" autocomplete="off" required>
                        @error('end_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Start Date Portion</label>
                        @foreach (['full' => 'Full day', 'am' => 'First half (AM)', 'pm' => 'Second half (PM)'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="start_half" id="start_half_{{ $value }}" value="{{ $value }}" {{ old('start_half', 'full') === $value ? 'checked' : '' }}>
                                <label class="form-check-label" for="start_half_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('start_half')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">End Date Portion</label>
                        @foreach (['full' => 'Full day', 'am' => 'First half (AM)', 'pm' => 'Second half (PM)'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="end_half" id="end_half_{{ $value }}" value="{{ $value }}" {{ old('end_half', 'full') === $value ? 'checked' : '' }}>
                                <label class="form-check-label" for="end_half_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        <div class="form-text">For single-day records, only the start portion is used.</div>
                        @error('end_half')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="reason" class="form-label">Reason</label>
                    <textarea id="reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $value)
                                <option value="{{ $value }}" {{ old('status', 'approved') === $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="applied_at" class="form-label">Applied Date (optional)</label>
                        <input type="text" id="applied_at" name="applied_at" value="{{ old('applied_at') }}" class="form-control @error('applied_at') is-invalid @enderror" autocomplete="off">
                        @error('applied_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3" id="decided_at_group">
                        <label for="decided_at" class="form-label">Decided Date (optional)</label>
                        <input type="text" id="decided_at" name="decided_at" value="{{ old('decided_at') }}" class="form-control @error('decided_at') is-invalid @enderror" autocomplete="off">
                        @error('decided_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="remarks" class="form-label">Remarks (optional)</label>
                    <textarea id="remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-text mb-3">
                    Historical records skip overlap checks and notification emails. If the status is "Approved" and the leave type affects balances, the employee's leave balance for the corresponding leave year will be deducted automatically.
                </div>

                <button type="submit" class="btn btn-primary">Create Record</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr('#start_date', { dateFormat: 'Y-m-d' });
            flatpickr('#end_date', { dateFormat: 'Y-m-d' });
            flatpickr('#applied_at', { dateFormat: 'Y-m-d' });
            flatpickr('#decided_at', { dateFormat: 'Y-m-d' });

            const statusSelect = document.getElementById('status');
            const decidedAtGroup = document.getElementById('decided_at_group');

            function toggleDecidedAt() {
                const show = ['approved', 'rejected'].includes(statusSelect.value);
                decidedAtGroup.style.display = show ? '' : 'none';
            }

            statusSelect.addEventListener('change', toggleDecidedAt);
            toggleDecidedAt();
        });
    </script>
@endpush

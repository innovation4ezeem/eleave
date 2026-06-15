@extends('layouts.app')

@section('title', 'Apply for Leave')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Apply for Leave</h1>
        <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Back to My Applications</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="leave_type_id" class="form-label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id"
                            class="form-select @error('leave_type_id') is-invalid @enderror" required>
                        <option value="">-- Select leave type --</option>
                        @foreach ($leaveTypes as $leaveType)
                            <option value="{{ $leaveType->id }}"
                                data-requires-document="{{ $leaveType->requires_document ? '1' : '0' }}"
                                {{ (string) old('leave_type_id') === (string) $leaveType->id ? 'selected' : '' }}>
                                {{ $leaveType->name }}
                                @if (isset($balances[$leaveType->id]))
                                    (remaining: {{ $balances[$leaveType->id]->remaining_days }} day(s))
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('leave_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="text" id="start_date" name="start_date"
                               value="{{ old('start_date') }}"
                               class="form-control @error('start_date') is-invalid @enderror"
                               autocomplete="off" required>
                        @error('start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="text" id="end_date" name="end_date"
                               value="{{ old('end_date') }}"
                               class="form-control @error('end_date') is-invalid @enderror"
                               autocomplete="off" required>
                        @error('end_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Start Date Portion</label>
                        @foreach (['full' => 'Full day', 'am' => 'First half (AM)', 'pm' => 'Second half (PM)'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="start_half" id="start_half_{{ $value }}"
                                       value="{{ $value }}" {{ old('start_half', 'full') === $value ? 'checked' : '' }}>
                                <label class="form-check-label" for="start_half_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('start_half')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3" id="end_half_group">
                        <label class="form-label d-block">End Date Portion</label>
                        @foreach (['full' => 'Full day', 'am' => 'First half (AM)', 'pm' => 'Second half (PM)'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="end_half" id="end_half_{{ $value }}"
                                       value="{{ $value }}" {{ old('end_half', 'full') === $value ? 'checked' : '' }}>
                                <label class="form-check-label" for="end_half_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('end_half')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="end_half_hint">Not used for single-day applications.</div>
                    </div>
                </div>

                <div class="alert alert-info" id="day_count_preview">
                    Total: <strong>0</strong> day(s)
                </div>

                <div class="mb-3">
                    <label for="reason" class="form-label">Reason</label>
                    <textarea id="reason" name="reason" rows="3"
                              class="form-control @error('reason') is-invalid @enderror"
                              required>{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="document" class="form-label">Supporting Document <span id="document_optional_label" class="text-muted">(optional)</span></label>
                    <input type="file" id="document" name="document"
                           class="form-control @error('document') is-invalid @enderror"
                           accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">PDF, JPG or PNG, max 5MB.</div>
                    @error('document')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Submit Application</button>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">My Leave Balances ({{ \App\Support\LeaveYear::current() }})</h2>
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($balances as $balance)
                                <tr>
                                    <td>{{ $balance->leaveType->name }}</td>
                                    <td class="text-end">{{ $balance->remaining_days }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted">No balances configured yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const previewBox = document.getElementById('day_count_preview');
            const endHalfGroup = document.getElementById('end_half_group');
            const endHalfHint = document.getElementById('end_half_hint');
            const leaveTypeSelect = document.getElementById('leave_type_id');
            const documentOptionalLabel = document.getElementById('document_optional_label');

            flatpickr(startDateInput, {
                dateFormat: 'Y-m-d',
                onChange: function (selectedDates, dateStr) {
                    if (endFp.selectedDates.length === 0 || endFp.selectedDates[0] < selectedDates[0]) {
                        endFp.setDate(dateStr, true);
                    }
                    endFp.set('minDate', dateStr);
                    recalculate();
                },
            });

            const endFp = flatpickr(endDateInput, {
                dateFormat: 'Y-m-d',
                onChange: recalculate,
            });

            document.querySelectorAll('input[name="start_half"], input[name="end_half"]').forEach(function (el) {
                el.addEventListener('change', recalculate);
            });

            leaveTypeSelect.addEventListener('change', function () {
                const option = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                const requiresDocument = option && option.dataset.requiresDocument === '1';
                documentOptionalLabel.style.display = requiresDocument ? 'none' : '';
            });

            function isWeekend(date) {
                const day = date.getDay();
                return day === 0 || day === 6;
            }

            function recalculate() {
                const startVal = startDateInput.value;
                const endVal = endDateInput.value;

                if (!startVal || !endVal) {
                    previewBox.innerHTML = 'Total: <strong>0</strong> day(s)';
                    return;
                }

                const start = new Date(startVal + 'T00:00:00');
                const end = new Date(endVal + 'T00:00:00');

                if (end < start) {
                    previewBox.innerHTML = 'Total: <strong>0</strong> day(s)';
                    return;
                }

                const isSingleDay = start.getTime() === end.getTime();
                endHalfGroup.style.display = isSingleDay ? 'none' : '';
                endHalfHint.style.display = isSingleDay ? '' : 'none';

                const startHalf = document.querySelector('input[name="start_half"]:checked').value;
                let endHalf = document.querySelector('input[name="end_half"]:checked').value;
                if (isSingleDay) {
                    endHalf = 'full';
                }

                let total = 0;
                const cursor = new Date(start.getTime());

                while (cursor <= end) {
                    if (!isWeekend(cursor)) {
                        let dayValue = 1;

                        if (cursor.getTime() === start.getTime() && startHalf !== 'full') {
                            dayValue = 0.5;
                        } else if (cursor.getTime() === end.getTime() && endHalf !== 'full') {
                            dayValue = 0.5;
                        }

                        total += dayValue;
                    }

                    cursor.setDate(cursor.getDate() + 1);
                }

                previewBox.innerHTML = 'Total: <strong>' + total + '</strong> day(s)';
            }

            recalculate();
        });
    </script>
@endpush

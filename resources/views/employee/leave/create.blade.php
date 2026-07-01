@extends('layouts.app')

@section('title', 'Apply for Leave')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Apply for Leave</h1>
        <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Back to My Applications</a>
    </div>

    @php
        $annualLeaveType = $leaveTypes->firstWhere('code', 'annual');
        $rewardLeaveType = $leaveTypes->firstWhere('code', 'reward');
        $annualBalance = $annualLeaveType && isset($balances[$annualLeaveType->id]) ? $balances[$annualLeaveType->id] : null;
        $annualRemaining = $annualBalance
            ? (float) $annualBalance->remaining_days
            : ($annualLeaveType ? (float) $annualLeaveType->default_annual_quota : 0);
    @endphp

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- AM / single leave type --}}
                <div class="mb-3">
                    <label for="leave_type_id" class="form-label" id="leave_type_label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id"
                            class="form-select @error('leave_type_id') is-invalid @enderror" required>
                        <option value="">-- Select leave type --</option>
                        @foreach ($leaveTypes as $leaveType)
                            <option value="{{ $leaveType->id }}"
                                data-requires-document="{{ $leaveType->requires_document ? '1' : '0' }}"
                                data-affects-balance="{{ $leaveType->affects_balance ? '1' : '0' }}"
                                data-remaining="{{ $balances[$leaveType->id]->remaining_days ?? $leaveType->default_annual_quota }}"
                                data-is-reward="{{ $leaveType->code === 'reward' ? '1' : '0' }}"
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

                {{-- PM leave type (split mode only) --}}
                <div class="mb-3" id="pm_leave_type_group" style="display:none">
                    <label for="leave_type_id_pm" class="form-label">PM Leave Type</label>
                    <select id="leave_type_id_pm" name="leave_type_id_pm"
                            class="form-select @error('leave_type_id_pm') is-invalid @enderror" disabled>
                        <option value="">-- Select leave type --</option>
                        @foreach ($leaveTypes as $leaveType)
                            <option value="{{ $leaveType->id }}"
                                data-affects-balance="{{ $leaveType->affects_balance ? '1' : '0' }}"
                                data-remaining="{{ $balances[$leaveType->id]->remaining_days ?? $leaveType->default_annual_quota }}"
                                data-is-reward="{{ $leaveType->code === 'reward' ? '1' : '0' }}"
                                {{ (string) old('leave_type_id_pm') === (string) $leaveType->id ? 'selected' : '' }}>
                                {{ $leaveType->name }}
                                @if (isset($balances[$leaveType->id]))
                                    (remaining: {{ $balances[$leaveType->id]->remaining_days }} day(s))
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('leave_type_id_pm')
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
                    <div class="col-md-6 mb-3" id="end_date_col">
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

                {{-- Split toggle (shown only for single-day selection) --}}
                <div class="mb-3" id="split_toggle_row" style="display:none">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="split_leave_checkbox">
                        <label class="form-check-label" for="split_leave_checkbox">
                            Use different leave types for AM and PM halves
                        </label>
                    </div>
                </div>
                <input type="hidden" name="split_leave" id="split_leave_input" value="0">
                <input type="hidden" id="start_half_split_hidden" name="start_half" value="am" disabled>

                <div class="row" id="start_half_row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Start Date Portion</label>
                        @foreach (['full' => 'Full day', 'am' => 'First half (AM)', 'pm' => 'Second half (PM)'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input start-half-radio" type="radio" name="start_half" id="start_half_{{ $value }}"
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
                {{-- Dummy end_half for split mode (not shown, value irrelevant for single day) --}}
                <input type="hidden" id="end_half_split_hidden" name="end_half" value="full" disabled>

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

                <div class="mb-3" id="document_group">
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
            @if ($rewardLeaveType)
                <div class="card mt-3 border-warning">
                    <div class="card-body">
                        <h2 class="h6 text-warning">Reward Leave Note</h2>
                        <p class="small mb-0">Reward Leave can only be applied when your Annual Leave balance is fully exhausted.</p>
                    </div>
                </div>
            @endif
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
            const pmLeaveTypeSelect = document.getElementById('leave_type_id_pm');
            const documentOptionalLabel = document.getElementById('document_optional_label');
            const splitToggleRow = document.getElementById('split_toggle_row');
            const splitCheckbox = document.getElementById('split_leave_checkbox');
            const splitInput = document.getElementById('split_leave_input');
            const startHalfRow = document.getElementById('start_half_row');
            const startHalfSplitHidden = document.getElementById('start_half_split_hidden');
            const endHalfSplitHidden = document.getElementById('end_half_split_hidden');
            const startHalfRadios = document.querySelectorAll('.start-half-radio');
            const endHalfRadios = document.querySelectorAll('input[name="end_half"]:not([type="hidden"])');
            const pmLeaveTypeGroup = document.getElementById('pm_leave_type_group');
            const leaveTypeLabel = document.getElementById('leave_type_label');
            const documentGroup = document.getElementById('document_group');
            const endDateCol = document.getElementById('end_date_col');

            const rewardLeaveTypeId = {{ $rewardLeaveType?->id ?? 'null' }};
            const annualLeaveRemaining = {{ (float) $annualRemaining }};

            flatpickr(startDateInput, {
                dateFormat: 'Y-m-d',
                onChange: function (selectedDates, dateStr) {
                    if (endFp.selectedDates.length === 0 || endFp.selectedDates[0] < selectedDates[0]) {
                        endFp.setDate(dateStr, true);
                    }
                    endFp.set('minDate', dateStr);
                    if (splitCheckbox.checked) {
                        endFp.setDate(dateStr, true);
                    }
                    recalculate();
                },
            });

            const endFp = flatpickr(endDateInput, {
                dateFormat: 'Y-m-d',
                onChange: recalculate,
            });

            document.querySelectorAll('.start-half-radio, input[name="end_half"]:not([type="hidden"])').forEach(function (el) {
                el.addEventListener('change', recalculate);
            });

            leaveTypeSelect.addEventListener('change', function () {
                const option = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                const requiresDocument = option && option.dataset.requiresDocument === '1';
                documentOptionalLabel.style.display = requiresDocument ? 'none' : '';
                recalculate();
            });

            pmLeaveTypeSelect.addEventListener('change', recalculate);

            splitCheckbox.addEventListener('change', function () {
                const isSplit = this.checked;
                splitInput.value = isSplit ? '1' : '0';

                if (isSplit) {
                    // Force end_date = start_date
                    endFp.setDate(startDateInput.value, true);
                    endDateCol.style.display = 'none';

                    // Switch to split UI
                    leaveTypeLabel.textContent = 'AM Leave Type';
                    pmLeaveTypeGroup.style.display = '';
                    pmLeaveTypeSelect.disabled = false;
                    startHalfRow.style.display = 'none';
                    startHalfRadios.forEach(r => r.disabled = true);
                    endHalfRadios.forEach(r => r.disabled = true);
                    startHalfSplitHidden.disabled = false;
                    endHalfSplitHidden.disabled = false;
                    documentGroup.style.display = 'none';
                } else {
                    endDateCol.style.display = '';
                    leaveTypeLabel.textContent = 'Leave Type';
                    pmLeaveTypeGroup.style.display = 'none';
                    pmLeaveTypeSelect.disabled = true;
                    startHalfRow.style.display = '';
                    startHalfRadios.forEach(r => r.disabled = false);
                    endHalfRadios.forEach(r => r.disabled = false);
                    startHalfSplitHidden.disabled = true;
                    endHalfSplitHidden.disabled = true;
                    documentGroup.style.display = '';
                }

                recalculate();
            });

            function isWeekend(date) {
                const day = date.getDay();
                return day === 0 || day === 6;
            }

            function balanceWarning(option, days) {
                if (!option || !option.value) return null;
                const typeId = parseInt(option.value);
                if (typeId === rewardLeaveTypeId && annualLeaveRemaining > 0) {
                    return 'Reward Leave requires Annual Leave to be exhausted (' + annualLeaveRemaining + ' AL day(s) remaining).';
                }
                if (option.dataset.affectsBalance === '1') {
                    const remaining = parseFloat(option.dataset.remaining);
                    if (!isNaN(remaining) && days > remaining) {
                        return 'Exceeds balance: only ' + remaining + ' day(s) of ' + option.text.split(' (')[0] + ' remaining.';
                    }
                }
                return null;
            }

            function recalculate() {
                const isSplit = splitCheckbox.checked;
                const startVal = startDateInput.value;
                const endVal = isSplit ? startVal : endDateInput.value;

                if (!startVal || !endVal) {
                    resetPreview();
                    return;
                }

                const start = new Date(startVal + 'T00:00:00');
                const end = new Date(endVal + 'T00:00:00');
                const isSingleDay = start.getTime() === end.getTime();

                // Show/hide split toggle
                splitToggleRow.style.display = isSingleDay ? '' : 'none';
                if (!isSingleDay && splitCheckbox.checked) {
                    splitCheckbox.checked = false;
                    splitCheckbox.dispatchEvent(new Event('change'));
                    return;
                }

                if (isSplit) {
                    const amOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                    const pmOption = pmLeaveTypeSelect.options[pmLeaveTypeSelect.selectedIndex];
                    const warnings = [];

                    const amWarn = balanceWarning(amOption, 0.5);
                    if (amWarn) warnings.push('AM: ' + amWarn);
                    const pmWarn = balanceWarning(pmOption, 0.5);
                    if (pmWarn) warnings.push('PM: ' + pmWarn);

                    previewBox.classList.remove('alert-info', 'alert-warning');
                    let html = 'Total: <strong>1</strong> day (0.5 AM + 0.5 PM)';
                    if (warnings.length > 0) {
                        html += '<br><small>' + warnings.join('<br>') + '</small>';
                        previewBox.classList.add('alert-warning');
                    } else {
                        previewBox.classList.add('alert-info');
                    }
                    previewBox.innerHTML = html;
                    return;
                }

                // Normal mode
                if (end < start) {
                    resetPreview();
                    return;
                }

                endHalfGroup.style.display = isSingleDay ? 'none' : '';
                endHalfHint.style.display = isSingleDay ? '' : 'none';

                const startHalf = document.querySelector('.start-half-radio:checked')?.value ?? 'full';
                let endHalf = document.querySelector('input[name="end_half"]:not([type="hidden"]):checked')?.value ?? 'full';
                if (isSingleDay) endHalf = 'full';

                let total = 0;
                const cursor = new Date(start.getTime());
                while (cursor <= end) {
                    if (!isWeekend(cursor)) {
                        let dayValue = 1;
                        if (cursor.getTime() === start.getTime() && startHalf !== 'full') dayValue = 0.5;
                        else if (cursor.getTime() === end.getTime() && endHalf !== 'full') dayValue = 0.5;
                        total += dayValue;
                    }
                    cursor.setDate(cursor.getDate() + 1);
                }

                previewBox.classList.remove('alert-info', 'alert-warning');
                let html = 'Total: <strong>' + total + '</strong> day(s)';

                const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                const warn = balanceWarning(selectedOption, total);
                if (warn) {
                    html += ' &mdash; ' + warn;
                    previewBox.classList.add('alert-warning');
                } else {
                    previewBox.classList.add('alert-info');
                }

                previewBox.innerHTML = html;
            }

            function resetPreview() {
                previewBox.classList.remove('alert-warning');
                previewBox.classList.add('alert-info');
                previewBox.innerHTML = 'Total: <strong>0</strong> day(s)';
            }

            recalculate();
        });
    </script>
@endpush

@extends('layouts.app')

@section('title', 'Leave Application Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Application Details</h1>
        <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Back to My Applications</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Leave Type</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->leaveType->name }}</dd>

                        <dt class="col-sm-4">Dates</dt>
                        <dd class="col-sm-8">
                            {{ $leaveApplication->start_date->format('d M Y') }}
                            @if (! $leaveApplication->start_date->equalTo($leaveApplication->end_date))
                                &ndash; {{ $leaveApplication->end_date->format('d M Y') }}
                            @endif
                        </dd>

                        <dt class="col-sm-4">Start Portion</dt>
                        <dd class="col-sm-8">{{ ucfirst($leaveApplication->start_half) }}</dd>

                        <dt class="col-sm-4">End Portion</dt>
                        <dd class="col-sm-8">{{ ucfirst($leaveApplication->end_half) }}</dd>

                        <dt class="col-sm-4">Total Days</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->total_days }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $leaveApplication->statusBadgeClass() }}">{{ ucfirst($leaveApplication->status) }}</span>
                        </dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8">{{ $leaveApplication->reason }}</dd>

                        @if ($leaveApplication->hasDocument())
                            <dt class="col-sm-4">Document</dt>
                            <dd class="col-sm-8">
                                <a href="{{ route('leave.document', $leaveApplication) }}">{{ $leaveApplication->document_original_name }}</a>
                            </dd>
                        @endif

                        @if ($leaveApplication->status !== 'pending')
                            <dt class="col-sm-4">Decided By</dt>
                            <dd class="col-sm-8">{{ $leaveApplication->decidedBy?->name ?? '—' }}</dd>

                            <dt class="col-sm-4">Decided At</dt>
                            <dd class="col-sm-8">{{ $leaveApplication->decided_at?->format('d M Y H:i') ?? '—' }}</dd>

                            @if ($leaveApplication->remarks)
                                <dt class="col-sm-4">Remarks</dt>
                                <dd class="col-sm-8">{{ $leaveApplication->remarks }}</dd>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>

            @if ($leaveApplication->status === 'pending')
                <form method="POST" action="{{ route('leave.cancel', $leaveApplication) }}"
                      onsubmit="return confirm('Are you sure you want to cancel this leave application?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Cancel Application</button>
                </form>
            @endif

            @if (in_array($leaveApplication->status, ['approved', 'rejected'], true))
                <div class="card mt-3">
                    <div class="card-body">
                        <h2 class="h6">Request a Change or Cancellation</h2>
                        <p class="small text-muted">Need to cancel this leave, or change its leave type/dates? Submit the new details below and your HOD will review them. If approved, the change is applied directly &mdash; no need to re-apply separately.</p>
                        <form method="POST" action="{{ route('leave.change-request', $leaveApplication) }}" id="change_request_form"
                              onsubmit="return confirm('Submit this change/cancellation request?');">
                            @csrf

                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="change_action" id="action_cancel" value="cancel" {{ old('change_action', 'cancel') === 'cancel' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="action_cancel">Cancel this leave entirely</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="change_action" id="action_amend" value="amend" {{ old('change_action') === 'amend' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="action_amend">Change leave type / dates</label>
                                </div>
                                @error('change_action')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div id="amend_fields" style="display:none">
                                <div class="mb-2">
                                    <label for="cr_leave_type_id" class="form-label">New Leave Type</label>
                                    <select id="cr_leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror">
                                        <option value="">-- Select leave type --</option>
                                        @foreach ($changeableLeaveTypes as $type)
                                            <option value="{{ $type->id }}" {{ (string) old('leave_type_id', $leaveApplication->leave_type_id) === (string) $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('leave_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label for="cr_start_date" class="form-label">New Start Date</label>
                                        <input type="date" id="cr_start_date" name="start_date" value="{{ old('start_date', $leaveApplication->start_date->format('Y-m-d')) }}" class="form-control @error('start_date') is-invalid @enderror">
                                        @error('start_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label for="cr_end_date" class="form-label">New End Date</label>
                                        <input type="date" id="cr_end_date" name="end_date" value="{{ old('end_date', $leaveApplication->end_date->format('Y-m-d')) }}" class="form-control @error('end_date') is-invalid @enderror">
                                        @error('end_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label d-block">Start Portion</label>
                                        @foreach (['full' => 'Full day', 'am' => 'AM', 'pm' => 'PM'] as $value => $label)
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="start_half" id="cr_start_half_{{ $value }}" value="{{ $value }}" {{ old('start_half', $leaveApplication->start_half) === $value ? 'checked' : '' }}>
                                                <label class="form-check-label" for="cr_start_half_{{ $value }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                        @error('start_half')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label d-block">End Portion</label>
                                        @foreach (['full' => 'Full day', 'am' => 'AM', 'pm' => 'PM'] as $value => $label)
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="end_half" id="cr_end_half_{{ $value }}" value="{{ $value }}" {{ old('end_half', $leaveApplication->end_half) === $value ? 'checked' : '' }}>
                                                <label class="form-check-label" for="cr_end_half_{{ $value }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                        @error('end_half')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label for="reason" class="form-label">Reason for the request</label>
                                <textarea id="reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-outline-warning">Submit Request</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($leaveApplication->status === 'change_requested')
                <div class="alert alert-info mt-3 mb-0">
                    @if ($leaveApplication->change_action === 'cancel')
                        Your cancellation request is pending HOD review.
                    @else
                        Your change request is pending HOD review &mdash;
                        proposed: <strong>{{ $leaveApplication->requestedLeaveType?->name }}</strong>,
                        {{ $leaveApplication->requested_start_date?->format('d M Y') }}
                        @if ($leaveApplication->requested_start_date && $leaveApplication->requested_end_date && ! $leaveApplication->requested_start_date->equalTo($leaveApplication->requested_end_date))
                            &ndash; {{ $leaveApplication->requested_end_date->format('d M Y') }}
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">History</h2>
                    <ul class="list-unstyled mb-0 small">
                        @foreach ($leaveApplication->logs as $log)
                            <li class="mb-2">
                                <div>{{ ucfirst($log->action) }}@if ($log->actor) by {{ $log->actor->name }}@endif</div>
                                <div class="text-muted">{{ $log->created_at->format('d M Y H:i') }}</div>
                                @if ($log->notes)
                                    <div>{{ $log->notes }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const amendRadio = document.getElementById('action_amend');
            const cancelRadio = document.getElementById('action_cancel');
            const amendFields = document.getElementById('amend_fields');

            if (!amendRadio) {
                return;
            }

            function toggle() {
                const show = amendRadio.checked;
                amendFields.style.display = show ? '' : 'none';
                amendFields.querySelectorAll('select, input').forEach(function (el) {
                    el.disabled = !show;
                });
            }

            amendRadio.addEventListener('change', toggle);
            cancelRadio.addEventListener('change', toggle);
            toggle();
        });
    </script>
@endpush

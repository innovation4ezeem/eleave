@extends('layouts.app')

@section('title', 'Edit Leave Balances')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Balances &mdash; {{ $user->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
    </div>

    <div class="mb-3">
        <div class="btn-group">
            <a href="{{ route('admin.users.balances.edit', ['user' => $user, 'leave_year' => $leaveYear - 1]) }}" class="btn btn-outline-secondary btn-sm">&laquo; {{ $leaveYear - 1 }}</a>
            <span class="btn btn-light btn-sm disabled">{{ $leaveYear }}</span>
            <a href="{{ route('admin.users.balances.edit', ['user' => $user, 'leave_year' => $leaveYear + 1]) }}" class="btn btn-outline-secondary btn-sm">{{ $leaveYear + 1 }} &raquo;</a>
        </div>
        <div class="form-text">Leave year runs 1 Jan {{ $leaveYear }} &ndash; 31 Dec {{ $leaveYear }}.</div>
    </div>

    @if ($locked)
        <div class="alert alert-secondary">
            <strong>This leave year is locked (archived).</strong> Balances are read-only.
            Unlock it under <a href="{{ route('admin.leave-years.index') }}">Leave Years</a> to make changes.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.balances.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="leave_year" value="{{ $leaveYear }}">

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th style="width: 110px;">Entitlement</th>
                                        <th style="width: 110px;">Adjustment</th>
                                        <th style="width: 110px;">Used</th>
                                        <th class="text-end">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($balances as $index => $balance)
                                        <tr>
                                            <td>
                                                {{ $balance->leaveType->name }}
                                                <input type="hidden" name="balances[{{ $index }}][id]" value="{{ $balance->id }}">
                                            </td>
                                            <td>
                                                <input type="number" step="0.5" min="0" name="balances[{{ $index }}][entitlement_days]" value="{{ old("balances.$index.entitlement_days", $balance->entitlement_days) }}" class="form-control form-control-sm balance-input" data-row="{{ $index }}" {{ $locked ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" step="0.5" name="balances[{{ $index }}][adjustment_days]" value="{{ old("balances.$index.adjustment_days", $balance->adjustment_days) }}" class="form-control form-control-sm balance-input" data-row="{{ $index }}" {{ $locked ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" step="0.5" min="0" name="balances[{{ $index }}][used_days]" value="{{ old("balances.$index.used_days", $balance->used_days) }}" class="form-control form-control-sm balance-input" data-row="{{ $index }}" {{ $locked ? 'disabled' : '' }}>
                                            </td>
                                            <td class="text-end">
                                                <strong><span id="remaining-{{ $index }}">{{ $balance->remaining_days }}</span></strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for changes (optional)</label>
                            <textarea id="reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" {{ $locked ? 'disabled' : '' }}>{{ old('reason') }}</textarea>
                            <div class="form-text">Recorded in the audit trail for any fields that change.</div>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @unless ($locked)
                            <button type="submit" class="btn btn-primary">Save Balances</button>
                        @endunless
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">Recent Adjustments</h2>
                    @if ($adjustments->isEmpty())
                        <p class="text-muted small mb-0">No adjustments recorded for this leave year.</p>
                    @else
                        <ul class="list-unstyled mb-0 small">
                            @foreach ($adjustments as $adjustment)
                                <li class="mb-2">
                                    <div>{{ $adjustment->leaveBalance->leaveType->name }} &mdash; {{ str_replace('_', ' ', $adjustment->field_changed) }}</div>
                                    <div>{{ $adjustment->old_value }} &rarr; {{ $adjustment->new_value }}</div>
                                    <div class="text-muted">{{ $adjustment->changedBy?->name ?? '—' }} on {{ $adjustment->created_at->format('d M Y H:i') }}</div>
                                    @if ($adjustment->reason)
                                        <div class="text-muted">"{{ $adjustment->reason }}"</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.balance-input').forEach((input) => {
            input.addEventListener('input', () => {
                const row = input.dataset.row;
                const entitlement = parseFloat(document.querySelector(`[name="balances[${row}][entitlement_days]"]`).value) || 0;
                const adjustment = parseFloat(document.querySelector(`[name="balances[${row}][adjustment_days]"]`).value) || 0;
                const used = parseFloat(document.querySelector(`[name="balances[${row}][used_days]"]`).value) || 0;
                document.getElementById(`remaining-${row}`).textContent = (entitlement + adjustment - used).toFixed(1);
            });
        });
    </script>
@endpush

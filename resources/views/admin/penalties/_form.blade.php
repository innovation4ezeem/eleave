<div class="row">
    <div class="col-md-6 mb-3">
        <label for="user_id" class="form-label">Employee</label>
        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
            <option value="">-- Select employee --</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" {{ (string) old('user_id', $penalty->user_id ?? '') === (string) $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}@if ($employee->employee_no) ({{ $employee->employee_no }})@endif
                </option>
            @endforeach
        </select>
        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="penalty_date" class="form-label">Date</label>
        <input type="date" id="penalty_date" name="penalty_date" value="{{ old('penalty_date', isset($penalty) ? $penalty->penalty_date->format('Y-m-d') : now()->format('Y-m-d')) }}" class="form-control @error('penalty_date') is-invalid @enderror" required>
        @error('penalty_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="penalty_type" class="form-label">Penalty Type</label>
        <select id="penalty_type" name="penalty_type" class="form-select @error('penalty_type') is-invalid @enderror" required>
            <option value="warning" {{ old('penalty_type', $penalty->penalty_type ?? 'warning') === 'warning' ? 'selected' : '' }}>Warning</option>
            <option value="cash" {{ old('penalty_type', $penalty->penalty_type ?? '') === 'cash' ? 'selected' : '' }}>Cash</option>
        </select>
        @error('penalty_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="penalty_level" class="form-label">Penalty Level</label>
        <select id="penalty_level" name="penalty_level" class="form-select @error('penalty_level') is-invalid @enderror" required>
            @foreach ([1, 2, 3] as $level)
                <option value="{{ $level }}" {{ (string) old('penalty_level', $penalty->penalty_level ?? 1) === (string) $level ? 'selected' : '' }}>Level {{ $level }}</option>
            @endforeach
        </select>
        @error('penalty_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3" id="charges_group">
        <label for="penalty_charges" class="form-label">Penalty Charges (RM)</label>
        <input type="number" step="0.01" min="0" id="penalty_charges" name="penalty_charges" value="{{ old('penalty_charges', $penalty->penalty_charges ?? '') }}" class="form-control @error('penalty_charges') is-invalid @enderror">
        <div class="form-text">Required for cash penalties.</div>
        @error('penalty_charges')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="reported_by" class="form-label">Reported By</label>
        <input type="text" id="reported_by" name="reported_by" value="{{ old('reported_by', $penalty->reported_by ?? auth()->user()->name) }}" class="form-control @error('reported_by') is-invalid @enderror">
        @error('reported_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mb-3">
        <label for="mistake" class="form-label">Mistake</label>
        <textarea id="mistake" name="mistake" rows="3" class="form-control @error('mistake') is-invalid @enderror" required>{{ old('mistake', $penalty->mistake ?? '') }}</textarea>
        @error('mistake')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mb-3">
        <label for="remarks" class="form-label">Remarks (optional)</label>
        <textarea id="remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks', $penalty->remarks ?? '') }}</textarea>
        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="document" class="form-label">Supporting Document (optional)</label>
        <input type="file" id="document" name="document" class="form-control @error('document') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
        <div class="form-text">PDF, JPG or PNG, max 5MB.
            @if (isset($penalty) && $penalty->hasDocument())
                Current: <a href="{{ route('penalties.document', $penalty) }}">{{ $penalty->document_original_name }}</a> (upload to replace)
            @endif
        </div>
        @error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('penalty_type');
            const chargesGroup = document.getElementById('charges_group');
            const chargesInput = document.getElementById('penalty_charges');

            function toggleCharges() {
                const isCash = typeSelect.value === 'cash';
                chargesGroup.style.display = isCash ? '' : 'none';
                chargesInput.required = isCash;
                if (!isCash) {
                    chargesInput.value = '';
                }
            }

            typeSelect.addEventListener('change', toggleCharges);
            toggleCharges();
        });
    </script>
@endpush

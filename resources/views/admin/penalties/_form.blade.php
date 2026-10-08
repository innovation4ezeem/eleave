@php
    $targetType   = old('target_type', isset($penalty) ? ($penalty->user_id ? 'individual' : 'department') : 'individual');
    $savedSubId   = isset($penalty) ? ($penalty->subdivision_id ?? null) : null;
@endphp

{{-- Target type toggle --}}
<div class="mb-3">
    <label class="form-label fw-semibold">Target</label>
    <div class="d-flex gap-3">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="target_individual"
                   value="individual" {{ $targetType === 'individual' ? 'checked' : '' }}>
            <label class="form-check-label" for="target_individual">Individual Employee</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="target_department"
                   value="department" {{ $targetType === 'department' ? 'checked' : '' }}>
            <label class="form-check-label" for="target_department">Department / Division</label>
        </div>
    </div>
</div>

<div class="row">
    {{-- Individual: employee picker --}}
    <div class="col-md-6 mb-3" id="field_employee">
        <label for="user_id" class="form-label">Employee</label>
        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror">
            <option value="">-- Select employee --</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}"
                        data-dept="{{ $employee->department_id }}"
                    {{ (string) old('user_id', $penalty->user_id ?? '') === (string) $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}@if ($employee->employee_no) ({{ $employee->employee_no }})@endif
                </option>
            @endforeach
        </select>
        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Department: dept picker (shown for dept target) --}}
    <div class="col-md-6 mb-3" id="field_department">
        <label for="department_id" class="form-label">Department</label>
        <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
            <option value="">-- Select department --</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}"
                    {{ (string) old('department_id', $penalty->department_id ?? '') === (string) $dept->id ? 'selected' : '' }}>
                    {{ $dept->name }}
                </option>
            @endforeach
        </select>
        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Subdivision (shown for both modes) --}}
    <div class="col-md-6 mb-3">
        <label for="subdivision_id" class="form-label">Subdivision <span class="text-muted small">(optional)</span></label>
        <select id="subdivision_id" name="subdivision_id" class="form-select @error('subdivision_id') is-invalid @enderror">
            <option value="">— whole department —</option>
        </select>
        @error('subdivision_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="penalty_date" class="form-label">Date</label>
        <input type="date" id="penalty_date" name="penalty_date"
               value="{{ old('penalty_date', isset($penalty) ? $penalty->penalty_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
               class="form-control @error('penalty_date') is-invalid @enderror" required>
        @error('penalty_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="penalty_type" class="form-label">Penalty Type</label>
        <select id="penalty_type" name="penalty_type" class="form-select @error('penalty_type') is-invalid @enderror" required>
            <option value="warning" {{ old('penalty_type', $penalty->penalty_type ?? 'warning') === 'warning' ? 'selected' : '' }}>Warning</option>
            <option value="cash"    {{ old('penalty_type', $penalty->penalty_type ?? '') === 'cash' ? 'selected' : '' }}>Cash</option>
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
        <input type="number" step="0.01" min="0" id="penalty_charges" name="penalty_charges"
               value="{{ old('penalty_charges', $penalty->penalty_charges ?? '') }}"
               class="form-control @error('penalty_charges') is-invalid @enderror">
        <div class="form-text">Required for cash penalties.</div>
        @error('penalty_charges')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="reported_by" class="form-label">Reported By</label>
        <input type="text" id="reported_by" name="reported_by"
               value="{{ old('reported_by', $penalty->reported_by ?? auth()->user()->name) }}"
               class="form-control @error('reported_by') is-invalid @enderror">
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

    // All subdivisions from server: [{id, name, department_id}]
    const allSubs = @json($subdivisions->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'department_id' => $s->department_id]));
    const savedSubId = {{ $savedSubId ?? 'null' }};

    // Employee -> dept map
    const empDeptMap = {};
    document.querySelectorAll('#user_id option[data-dept]').forEach(function (opt) {
        empDeptMap[opt.value] = opt.dataset.dept || null;
    });

    function populateSubs(deptId, restore) {
        const select = document.getElementById('subdivision_id');
        const current = restore !== undefined ? restore : (select.value || null);
        select.innerHTML = '<option value="">— whole department —</option>';
        const filtered = deptId
            ? allSubs.filter(s => s.department_id == deptId || s.department_id == null)
            : allSubs; // no dept selected: show all
        filtered.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (String(s.id) === String(current)) opt.selected = true;
            select.appendChild(opt);
        });
    }

    // Target type toggle
    const fieldEmployee   = document.getElementById('field_employee');
    const fieldDepartment = document.getElementById('field_department');
    const empSelect       = document.getElementById('user_id');
    const deptSelect      = document.getElementById('department_id');

    function applyTarget() {
        const isIndividual = document.getElementById('target_individual').checked;
        fieldEmployee.style.display   = isIndividual ? '' : 'none';
        fieldDepartment.style.display = isIndividual ? 'none' : '';
        // clear required-like behaviour
        empSelect.required  = isIndividual;
        deptSelect.required = !isIndividual;

        const deptId = isIndividual
            ? (empDeptMap[empSelect.value] || null)
            : (deptSelect.value || null);
        populateSubs(deptId, savedSubId);
    }

    document.querySelectorAll('input[name="target_type"]').forEach(function (r) {
        r.addEventListener('change', applyTarget);
    });

    // Employee change -> update subs
    empSelect.addEventListener('change', function () {
        populateSubs(empDeptMap[this.value] || null, null);
    });

    // Dept change -> update subs
    deptSelect.addEventListener('change', function () {
        populateSubs(this.value || null, null);
    });

    // Cash toggle
    const typeSelect   = document.getElementById('penalty_type');
    const chargesGroup = document.getElementById('charges_group');
    const chargesInput = document.getElementById('penalty_charges');

    function toggleCharges() {
        const isCash = typeSelect.value === 'cash';
        chargesGroup.style.display = isCash ? '' : 'none';
        chargesInput.required = isCash;
        if (!isCash) chargesInput.value = '';
    }
    typeSelect.addEventListener('change', toggleCharges);
    toggleCharges();

    // Init
    applyTarget();
});
</script>
@endpush

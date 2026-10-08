<div class="row">
    <div class="col-md-6 mb-3">
        <label for="user_id" class="form-label">Employee</label>
        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
            <option value="">-- Select employee --</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" {{ (string) old('user_id', $warningLetter->user_id ?? '') === (string) $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}@if ($employee->employee_no) ({{ $employee->employee_no }})@endif
                </option>
            @endforeach
        </select>
        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="issued_date" class="form-label">Date Issued</label>
        <input type="date" id="issued_date" name="issued_date" value="{{ old('issued_date', isset($warningLetter) ? $warningLetter->issued_date->format('Y-m-d') : now()->format('Y-m-d')) }}" class="form-control @error('issued_date') is-invalid @enderror" required>
        @error('issued_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="level" class="form-label">Level</label>
        <input type="text" id="level" name="level" list="level-suggestions" maxlength="100" value="{{ old('level', $warningLetter->level ?? '') }}" class="form-control @error('level') is-invalid @enderror" placeholder="e.g. 1st Written" required>
        <datalist id="level-suggestions">
            @foreach (config('warning_letters.level_suggestions', []) as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
        <div class="form-text">Pick a suggestion or type your own.</div>
        @error('level')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="file" class="form-label">Warning Letter File{{ isset($warningLetter) ? ' (optional)' : '' }}</label>
        <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" {{ isset($warningLetter) ? '' : 'required' }}>
        <div class="form-text">PDF, JPG or PNG, max {{ number_format(config('warning_letters.max_kb', 5120) / 1024, 0) }}MB.
            @if (isset($warningLetter))
                Current: <a href="{{ route('warning-letters.preview', $warningLetter) }}" target="_blank">{{ $warningLetter->original_filename }}</a> (upload to replace)
            @endif
        </div>
        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes (optional)</label>
        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $warningLetter->notes ?? '') }}</textarea>
        <div class="form-text">Admin only. Never shown to the employee or the HOD.</div>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

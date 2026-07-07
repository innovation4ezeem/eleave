{{-- Expects: $profileUser (User with workExperiences loaded) --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Working Experience</span>
        @can('manage-profile', $profileUser)
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addExperience">+ Add</button>
        @endcan
    </div>
    <div class="card-body">
        @can('manage-profile', $profileUser)
            <div class="collapse mb-3 {{ old('_form') === 'exp_add' ? 'show' : '' }}" id="addExperience">
                <form method="POST" action="{{ route('experiences.store', $profileUser) }}" class="border rounded p-3">
                    @csrf
                    <input type="hidden" name="_form" value="exp_add">
                    <div class="mb-2">
                        <label class="form-label mb-0">Job Title / Role</label>
                        <input type="text" name="title" value="{{ old('_form') === 'exp_add' ? old('title') : '' }}" class="form-control form-control-sm @if(old('_form')==='exp_add') @error('title') is-invalid @enderror @endif" required>
                        @if(old('_form')==='exp_add') @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label mb-0">Start Date</label>
                            <input type="date" name="start_date" value="{{ old('_form') === 'exp_add' ? old('start_date') : '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label mb-0">End Date <span class="text-muted small">(blank = current)</span></label>
                            <input type="date" name="end_date" value="{{ old('_form') === 'exp_add' ? old('end_date') : '' }}" class="form-control form-control-sm @if(old('_form')==='exp_add') @error('end_date') is-invalid @enderror @endif">
                            @if(old('_form')==='exp_add') @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Notes</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('_form') === 'exp_add' ? old('notes') : '' }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add Experience</button>
                </form>
            </div>
        @endcan

        @forelse ($profileUser->workExperiences as $exp)
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>{{ $exp->title }}</strong>
                        <div class="text-muted small">
                            {{ $exp->start_date?->format('M Y') ?? '—' }} &ndash;
                            {{ $exp->end_date?->format('M Y') ?? 'Present' }}
                        </div>
                        @if ($exp->notes)
                            <div class="small mt-1" style="white-space: pre-line;">{{ $exp->notes }}</div>
                        @endif
                    </div>
                    @can('manage-profile', $profileUser)
                        <div class="text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editExp{{ $exp->id }}">Edit</button>
                            <form method="POST" action="{{ route('experiences.destroy', $exp) }}" class="d-inline" onsubmit="return confirm('Delete this experience?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    @endcan
                </div>
                @can('manage-profile', $profileUser)
                    <div class="collapse mt-2 {{ old('_form') === 'exp_edit_'.$exp->id ? 'show' : '' }}" id="editExp{{ $exp->id }}">
                        <form method="POST" action="{{ route('experiences.update', $exp) }}" class="border rounded p-3 bg-light">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="exp_edit_{{ $exp->id }}">
                            @php $ef = old('_form') === 'exp_edit_'.$exp->id; @endphp
                            <div class="mb-2">
                                <label class="form-label mb-0">Job Title / Role</label>
                                <input type="text" name="title" value="{{ $ef ? old('title') : $exp->title }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label mb-0">Start Date</label>
                                    <input type="date" name="start_date" value="{{ $ef ? old('start_date') : $exp->start_date?->format('Y-m-d') }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label mb-0">End Date</label>
                                    <input type="date" name="end_date" value="{{ $ef ? old('end_date') : $exp->end_date?->format('Y-m-d') }}" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-0">Notes</label>
                                <textarea name="notes" rows="2" class="form-control form-control-sm">{{ $ef ? old('notes') : $exp->notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        </form>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-muted mb-0">No working experience recorded yet.</p>
        @endforelse
    </div>
</div>

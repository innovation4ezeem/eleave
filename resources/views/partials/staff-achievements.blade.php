{{-- Expects: $profileUser (User with staffAchievements loaded) --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Achievement</span>
        @can('manage-profile', $profileUser)
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addStaffAchievement">+ Add</button>
        @endcan
    </div>
    <div class="card-body">
        @can('manage-profile', $profileUser)
            <div class="collapse mb-3 {{ old('_form') === 'sach_add' ? 'show' : '' }}" id="addStaffAchievement">
                <form method="POST" action="{{ route('staff-achievements.store', $profileUser) }}" class="border rounded p-3">
                    @csrf
                    <input type="hidden" name="_form" value="sach_add">
                    <div class="mb-2">
                        <label class="form-label mb-0">Title</label>
                        <input type="text" name="title" value="{{ old('_form') === 'sach_add' ? old('title') : '' }}" class="form-control form-control-sm @if(old('_form')==='sach_add') @error('title') is-invalid @enderror @endif" required>
                        @if(old('_form')==='sach_add') @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Date <span class="text-muted small">(optional)</span></label>
                        <input type="date" name="achieved_on" value="{{ old('_form') === 'sach_add' ? old('achieved_on') : '' }}" class="form-control form-control-sm @if(old('_form')==='sach_add') @error('achieved_on') is-invalid @enderror @endif">
                        @if(old('_form')==='sach_add') @error('achieved_on') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                        <div class="form-text small">Leave blank if only the year matters — you can note it in the title (e.g. "Employee of the Year 2026").</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Description</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm">{{ old('_form') === 'sach_add' ? old('description') : '' }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add Achievement</button>
                </form>
            </div>
        @endcan

        @forelse ($profileUser->staffAchievements as $sach)
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>{{ $sach->title }}</strong>
                        @if ($sach->achieved_on)
                            <div class="text-muted small">{{ $sach->achieved_on->format('d M Y') }}</div>
                        @endif
                        @if ($sach->description)
                            <div class="small mt-1" style="white-space: pre-line;">{{ $sach->description }}</div>
                        @endif
                    </div>
                    @can('manage-profile', $profileUser)
                        <div class="text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editSAch{{ $sach->id }}">Edit</button>
                            <form method="POST" action="{{ route('staff-achievements.destroy', $sach) }}" class="d-inline" onsubmit="return confirm('Delete this achievement?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    @endcan
                </div>
                @can('manage-profile', $profileUser)
                    <div class="collapse mt-2 {{ old('_form') === 'sach_edit_'.$sach->id ? 'show' : '' }}" id="editSAch{{ $sach->id }}">
                        <form method="POST" action="{{ route('staff-achievements.update', $sach) }}" class="border rounded p-3 bg-light">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="sach_edit_{{ $sach->id }}">
                            @php $sf = old('_form') === 'sach_edit_'.$sach->id; @endphp
                            <div class="mb-2">
                                <label class="form-label mb-0">Title</label>
                                <input type="text" name="title" value="{{ $sf ? old('title') : $sach->title }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-0">Date <span class="text-muted small">(optional)</span></label>
                                <input type="date" name="achieved_on" value="{{ $sf ? old('achieved_on') : $sach->achieved_on?->format('Y-m-d') }}" class="form-control form-control-sm">
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-0">Description</label>
                                <textarea name="description" rows="2" class="form-control form-control-sm">{{ $sf ? old('description') : $sach->description }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        </form>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-muted mb-0">No achievements recorded yet.</p>
        @endforelse
    </div>
</div>

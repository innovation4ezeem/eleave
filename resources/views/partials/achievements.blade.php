{{-- Expects: $profileUser (User with achievements loaded) --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Achievements</span>
        @can('manage-profile', $profileUser)
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addAchievement">+ Add</button>
        @endcan
    </div>
    <div class="card-body">
        @can('manage-profile', $profileUser)
            <div class="collapse mb-3 {{ old('_form') === 'ach_add' ? 'show' : '' }}" id="addAchievement">
                <form method="POST" action="{{ route('achievements.store', $profileUser) }}" class="border rounded p-3">
                    @csrf
                    <input type="hidden" name="_form" value="ach_add">
                    <div class="mb-2">
                        <label class="form-label mb-0">Title</label>
                        <input type="text" name="title" value="{{ old('_form') === 'ach_add' ? old('title') : '' }}" class="form-control form-control-sm @if(old('_form')==='ach_add') @error('title') is-invalid @enderror @endif" required>
                        @if(old('_form')==='ach_add') @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Date</label>
                        <input type="date" name="achieved_on" value="{{ old('_form') === 'ach_add' ? old('achieved_on') : '' }}" class="form-control form-control-sm @if(old('_form')==='ach_add') @error('achieved_on') is-invalid @enderror @endif" required>
                        @if(old('_form')==='ach_add') @error('achieved_on') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Description</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm">{{ old('_form') === 'ach_add' ? old('description') : '' }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add Achievement</button>
                </form>
            </div>
        @endcan

        @forelse ($profileUser->achievements as $ach)
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>{{ $ach->title }}</strong>
                        <div class="text-muted small">{{ $ach->achieved_on?->format('d M Y') ?? '—' }}</div>
                        @if ($ach->description)
                            <div class="small mt-1" style="white-space: pre-line;">{{ $ach->description }}</div>
                        @endif
                    </div>
                    @can('manage-profile', $profileUser)
                        <div class="text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editAch{{ $ach->id }}">Edit</button>
                            <form method="POST" action="{{ route('achievements.destroy', $ach) }}" class="d-inline" onsubmit="return confirm('Delete this achievement?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    @endcan
                </div>
                @can('manage-profile', $profileUser)
                    <div class="collapse mt-2 {{ old('_form') === 'ach_edit_'.$ach->id ? 'show' : '' }}" id="editAch{{ $ach->id }}">
                        <form method="POST" action="{{ route('achievements.update', $ach) }}" class="border rounded p-3 bg-light">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="ach_edit_{{ $ach->id }}">
                            @php $af = old('_form') === 'ach_edit_'.$ach->id; @endphp
                            <div class="mb-2">
                                <label class="form-label mb-0">Title</label>
                                <input type="text" name="title" value="{{ $af ? old('title') : $ach->title }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-0">Date</label>
                                <input type="date" name="achieved_on" value="{{ $af ? old('achieved_on') : $ach->achieved_on?->format('Y-m-d') }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-0">Description</label>
                                <textarea name="description" rows="2" class="form-control form-control-sm">{{ $af ? old('description') : $ach->description }}</textarea>
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

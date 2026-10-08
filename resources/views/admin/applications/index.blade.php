@extends('layouts.app')

@section('title', 'Leave Applications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leave Applications</h1>
    </div>

    <form method="GET" action="{{ route('admin.applications.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Employee name, email, no.">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled', 'change_requested'] as $value)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Employee</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ (string) request('user_id') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (auth()->user()->isAdmin())
            <div class="col-md-2">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">Leave Type</label>
                <select name="leave_type_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}" {{ (string) request('leave_type_id') === (string) $leaveType->id ? 'selected' : '' }}>{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.applications.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>{{ $application->user?->name ?? '(Deleted)' }}</td>
                            <td>{{ $application->department?->name ?? '—' }}</td>
                            <td>{{ $application->leaveType->name }}</td>
                            <td>
                                {{ $application->start_date->format('d M Y') }}
                                @if (! $application->start_date->equalTo($application->end_date))
                                    &ndash; {{ $application->end_date->format('d M Y') }}
                                @endif
                            </td>
                            <td>{{ $application->total_days }}</td>
                            <td>
                                <span class="badge {{ $application->statusBadgeClass() }}" role="button"
                                      style="cursor: pointer;" title="Click to view details"
                                      data-bs-toggle="collapse" data-bs-target="#detail-{{ $application->id }}"
                                      aria-expanded="false" aria-controls="detail-{{ $application->id }}">
                                    {{ ucfirst(str_replace('_', ' ', $application->status)) }} &#9662;
                                </span>
                            </td>
                            <td>{{ $application->applied_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.applications.show', $application) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <tr class="table-active-none">
                            <td colspan="8" class="p-0 border-0">
                                <div class="collapse" id="detail-{{ $application->id }}">
                                    <div class="p-3 bg-light border-top">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <dl class="row mb-0 small">
                                                    <dt class="col-5">Employee</dt>
                                                    <dd class="col-7">{{ $application->user?->name ?? '(Deleted)' }}</dd>
                                                    <dt class="col-5">Department</dt>
                                                    <dd class="col-7">{{ $application->department?->name ?? '—' }}</dd>
                                                    <dt class="col-5">Leave Type</dt>
                                                    <dd class="col-7">{{ $application->leaveType->name }}</dd>
                                                    <dt class="col-5">Dates</dt>
                                                    <dd class="col-7">
                                                        {{ $application->start_date->format('d M Y') }}
                                                        @if (! $application->start_date->equalTo($application->end_date))
                                                            &ndash; {{ $application->end_date->format('d M Y') }}
                                                        @endif
                                                        @if ($application->start_half !== 'full' || $application->end_half !== 'full')
                                                            <span class="text-muted">({{ strtoupper($application->start_half) }}@if($application->end_half !== 'full') &ndash; {{ strtoupper($application->end_half) }}@endif)</span>
                                                        @endif
                                                    </dd>
                                                    <dt class="col-5">Total Days</dt>
                                                    <dd class="col-7">{{ $application->total_days }}</dd>
                                                    <dt class="col-5">Status</dt>
                                                    <dd class="col-7"><span class="badge {{ $application->statusBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</span></dd>
                                                    <dt class="col-5">Applied At</dt>
                                                    <dd class="col-7">{{ $application->applied_at?->format('d M Y H:i') ?? '—' }}</dd>
                                                    <dt class="col-5">Reason</dt>
                                                    <dd class="col-7" style="white-space: pre-line;">{{ $application->reason ?: '—' }}</dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                @if ($application->hasDocument())
                                                    <div class="small text-muted mb-1">Supporting Document</div>
                                                    @if ($application->isImageDocument())
                                                        <a href="{{ route('admin.applications.document.preview', $application) }}" target="_blank" rel="noopener">
                                                            <img src="{{ route('admin.applications.document.preview', $application) }}"
                                                                 alt="{{ $application->document_original_name }}"
                                                                 class="img-fluid border rounded mb-2" style="max-height: 260px;">
                                                        </a>
                                                    @elseif ($application->isPdfDocument())
                                                        <iframe src="{{ route('admin.applications.document.preview', $application) }}"
                                                                class="border rounded mb-2" style="width: 100%; height: 280px;"
                                                                title="{{ $application->document_original_name }}"></iframe>
                                                    @endif
                                                    <div class="small">
                                                        <a href="{{ route('admin.applications.document.preview', $application) }}" target="_blank" rel="noopener">Open in new tab</a>
                                                        &middot;
                                                        <a href="{{ route('admin.applications.document', $application) }}">Download</a>
                                                    </div>
                                                @else
                                                    <div class="small text-muted">No supporting document.</div>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($application->status === 'pending')
                                            @can('decide', $application)
                                                <hr class="my-3">
                                                <form method="POST" action="{{ route('admin.applications.approve', $application) }}">
                                                    @csrf
                                                    <div class="mb-2">
                                                        <label class="form-label mb-0 small">Reason / Remarks <span class="text-muted">(optional)</span></label>
                                                        <textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Optional note recorded with your decision"></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-success btn-sm"
                                                            formaction="{{ route('admin.applications.approve', $application) }}">Approve</button>
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                            formaction="{{ route('admin.applications.reject', $application) }}">Reject</button>
                                                </form>
                                            @endcan
                                        @elseif ($application->status === 'change_requested')
                                            <hr class="my-3">
                                            <div class="small text-muted">A change has been requested for this application. Use <strong>View</strong> to review and decide on the change.</div>
                                        @else
                                            <hr class="my-3">
                                            <div class="small text-muted">This application is <strong>{{ ucfirst(str_replace('_', ' ', $application->status)) }}</strong> — status is final and cannot be changed here.</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No leave applications found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $applications->links() }}
    </div>
@endsection

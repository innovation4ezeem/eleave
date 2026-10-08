@extends('layouts.app')

@section('title', 'Penalty Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Penalty Details</h1>
        <div>
            <a href="{{ route('admin.penalties.edit', $penalty) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
            <a href="{{ route('admin.penalties.index') }}" class="btn btn-outline-secondary btn-sm">Back to Penalties</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Target</dt>
                        <dd class="col-sm-8">
                            @if ($penalty->user_id)
                                <span class="badge bg-secondary me-1">Individual</span>
                                {{ $penalty->user?->name ?? '(Deleted)' }}
                                @if ($penalty->user?->employee_no)({{ $penalty->user->employee_no }})@endif
                            @else
                                <span class="badge bg-info text-dark me-1">Department</span>
                                {{ $penalty->department?->name ?? '—' }}
                            @endif
                        </dd>

                        @if ($penalty->user_id)
                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8">{{ $penalty->department?->name ?? '—' }}</dd>
                        @endif

                        @if ($penalty->subdivision)
                        <dt class="col-sm-4">Subdivision</dt>
                        <dd class="col-sm-8">{{ $penalty->subdivision->name }}</dd>
                        @endif

                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8">{{ $penalty->penalty_date->format('d M Y') }}</dd>

                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8"><span class="badge {{ $penalty->typeBadgeClass() }}">{{ ucfirst($penalty->penalty_type) }}</span></dd>

                        <dt class="col-sm-4">Level</dt>
                        <dd class="col-sm-8">{{ $penalty->levelLabel() }}</dd>

                        @if ($penalty->isCash())
                            <dt class="col-sm-4">Charges</dt>
                            <dd class="col-sm-8">RM {{ number_format((float) $penalty->penalty_charges, 2) }}</dd>

                            <dt class="col-sm-4">Cash Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge {{ $penalty->statusBadgeClass() }}">{{ ucfirst($penalty->collection_status) }}</span>
                                @if ($penalty->collection_status === 'collected')
                                    <span class="text-muted small">by {{ $penalty->collectedBy?->name ?? '—' }} on {{ $penalty->collected_at?->format('d M Y H:i') }}</span>
                                @endif
                            </dd>
                        @endif

                        <dt class="col-sm-4">Mistake</dt>
                        <dd class="col-sm-8">{{ $penalty->mistake }}</dd>

                        <dt class="col-sm-4">Reported By</dt>
                        <dd class="col-sm-8">{{ $penalty->reported_by }}</dd>

                        @if ($penalty->remarks)
                            <dt class="col-sm-4">Remarks</dt>
                            <dd class="col-sm-8">{{ $penalty->remarks }}</dd>
                        @endif

                        @if ($penalty->hasDocument())
                            <dt class="col-sm-4">Document</dt>
                            <dd class="col-sm-8"><a href="{{ route('penalties.document', $penalty) }}">{{ $penalty->document_original_name }}</a></dd>
                        @endif

                        <dt class="col-sm-4">Recorded By</dt>
                        <dd class="col-sm-8">{{ $penalty->createdBy?->name ?? '—' }} on {{ $penalty->created_at->format('d M Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if (auth()->user()->isAdmin() && $penalty->isCash() && $penalty->collection_status === 'pending')
                <div class="card border-warning">
                    <div class="card-body">
                        <h2 class="h6">Cash Collection</h2>
                        <p class="small text-muted">Mark this cash penalty as collected once the money has been received.</p>
                        <form method="POST" action="{{ route('admin.penalties.collect', $penalty) }}" onsubmit="return confirm('Mark this cash penalty as collected?');">
                            @csrf
                            <button type="submit" class="btn btn-success">Mark as Collected</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'My Penalties')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Penalties &amp; Warnings</h1>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Scope</th>
                        <th>Type</th>
                        <th>Level</th>
                        <th>Mistake</th>
                        <th>Charges</th>
                        <th>Reported By</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penalties as $penalty)
                        <tr>
                            <td>{{ $penalty->penalty_date->format('d M Y') }}</td>
                            <td>
                                @if ($penalty->user_id)
                                    <span class="badge bg-secondary">Personal</span>
                                @elseif ($penalty->subdivision_id)
                                    <span class="badge bg-info text-dark" title="{{ $penalty->department?->name }}">
                                        {{ $penalty->subdivision?->name }}
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark" title="{{ $penalty->department?->name }}">
                                        Dept-wide
                                    </span>
                                @endif
                            </td>
                            <td><span class="badge {{ $penalty->typeBadgeClass() }}">{{ ucfirst($penalty->penalty_type) }}</span></td>
                            <td>{{ $penalty->levelLabel() }}</td>
                            <td>{{ $penalty->mistake }}</td>
                            <td>{{ $penalty->isCash() ? 'RM '.number_format((float) $penalty->penalty_charges, 2) : '—' }}</td>
                            <td>{{ $penalty->reported_by }}</td>
                            <td>
                                @if ($penalty->hasDocument())
                                    <a href="{{ route('penalties.document', $penalty) }}">View</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">You have no penalties or warnings on record.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $penalties->links() }}
    </div>
@endsection

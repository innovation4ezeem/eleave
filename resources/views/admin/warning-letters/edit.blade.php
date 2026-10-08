@extends('layouts.app')

@section('title', 'Edit Warning Letter')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Warning Letter</h1>
        <a href="{{ route('admin.warning-letters.index') }}" class="btn btn-outline-secondary btn-sm">Back to Warning Letters</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.warning-letters.update', $warningLetter) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.warning-letters._form')
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    @can('viewHistory', $warningLetter)
        <div class="card mt-4">
            <div class="card-header">
                <strong>History</strong>
                <span class="text-muted small ms-2">
                    Issued by {{ $warningLetter->uploader?->name ?? '—' }}@if ($warningLetter->updater), last changed by {{ $warningLetter->updater->name }}@endif
                    &middot; visible to admins only
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td class="text-nowrap">{{ $event->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $event->actor?->name ?? '—' }}</td>
                                <td>{{ $event->label() }}</td>
                                <td class="text-muted small">{{ $event->details }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No history recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
@endsection

@extends('layouts.app')

@section('title', auth()->user()->isHod() ? 'Warning Letters' : 'My Warning Letters')

@section('content')
    @php $isHod = auth()->user()->isHod(); @endphp
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ $isHod ? 'Warning Letters' : 'My Warning Letters' }}</h1>
    </div>

    @if ($isHod)
        <p class="text-muted">Warning letters for employees in your department, and your own.</p>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date Issued</th>
                        @if ($isHod)
                            <th>Employee</th>
                        @endif
                        <th>Level</th>
                        <th>Letter</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($letters as $letter)
                        <tr>
                            <td>{{ $letter->issued_date->format('d M Y') }}</td>
                            @if ($isHod)
                                <td>
                                    {{ $letter->employee?->name ?? '(Deleted)' }}
                                    @if ($letter->user_id === auth()->id())
                                        <span class="badge bg-secondary ms-1">You</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $letter->level }}</td>
                            <td><a href="{{ route('warning-letters.download', $letter) }}">Download</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isHod ? 4 : 3 }}" class="text-center text-muted py-4">
                                {{ $isHod ? 'No warning letters found.' : 'You have no warning letters on record.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $letters->links() }}
    </div>
@endsection

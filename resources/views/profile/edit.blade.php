@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Profile</h1>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Profile Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8">{{ $user->name }}</dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">{{ $user->email }}</dd>

                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><span class="badge bg-secondary text-uppercase">{{ $user->role }}</span></dd>

                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8">{{ $user->department?->name ?? '—' }}</dd>

                        <dt class="col-sm-4">Designation</dt>
                        <dd class="col-sm-8">{{ $user->designation ?? '—' }}</dd>

                        <dt class="col-sm-4">Employee No.</dt>
                        <dd class="col-sm-8">{{ $user->employee_no ?? '—' }}</dd>

                        <dt class="col-sm-4">Joined Date</dt>
                        <dd class="col-sm-8">{{ $user->joined_at?->format('d M Y') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Change Password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            @include('partials.work-experiences', ['profileUser' => $user])
        </div>
        <div class="col-lg-4">
            @include('partials.staff-achievements', ['profileUser' => $user])
        </div>
        <div class="col-lg-4">
            @include('partials.achievements', ['profileUser' => $user])
        </div>
    </div>

    @if ($user->id === auth()->id() && $user->warningLetters()->exists())
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        Warning Letters
                        <a href="{{ route('warning-letters.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Date Issued</th>
                                    <th>Level</th>
                                    <th>Letter</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($user->warningLetters()->latest('issued_date')->get()->take(3) as $letter)
                                    <tr>
                                        <td class="ps-3">{{ $letter->issued_date->format('d M Y') }}</td>
                                        <td>{{ $letter->level }}</td>
                                        <td>
                                            <a href="{{ route('warning-letters.download', $letter) }}">Download</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

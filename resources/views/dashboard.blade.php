@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="h3 mb-4">Welcome, {{ auth()->user()->name }}</h1>

    <section class="mb-4">
        <h2 class="h5 text-muted mb-3">E-Leave</h2>

        @if (auth()->user()->isAdmin())
            <div class="row g-3">
                <div class="col-md-4">
                    <a href="{{ route('admin.applications.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Leave Applications</h3>
                                <p class="text-muted mb-0">Review and approve employee leave requests.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('admin.historical.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Historical Leave</h3>
                                <p class="text-muted mb-0">Migrate past leave records from the old system.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('leave.create') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Apply for Leave</h3>
                                <p class="text-muted mb-0">Submit a new leave application for yourself.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @elseif (auth()->user()->isHod())
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="{{ route('admin.applications.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Leave Applications</h3>
                                <p class="text-muted mb-0">Review and approve your department's leave requests.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.historical.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Historical Leave</h3>
                                <p class="text-muted mb-0">View past leave records for your department.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('leave.create') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Apply for Leave</h3>
                                <p class="text-muted mb-0">Submit a new leave application for yourself.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.users.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">My Department</h3>
                                <p class="text-muted mb-0">View and manage employees in your department.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @else
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="{{ route('leave.create') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Apply for Leave</h3>
                                <p class="text-muted mb-0">Submit a new leave application.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('leave.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">My Leave Applications</h3>
                                <p class="text-muted mb-0">View your leave application history.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('balances.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">My Leave Balance</h3>
                                <p class="text-muted mb-0">View your remaining leave balances.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('leave.report') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Leave Report</h3>
                                <p class="text-muted mb-0">View and print your leave records.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @endif
    </section>

    <section class="mb-4">
        <h2 class="h5 text-muted mb-3">Discipline</h2>
        <div class="row g-3">
            @if (auth()->user()->isAdminOrHod())
                <div class="col-md-4">
                    <a href="{{ route('admin.penalties.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Penalties &amp; Warnings</h3>
                                <p class="text-muted mb-0">Issue and manage warnings and cash penalties{{ auth()->user()->isHod() ? ' for your department' : '' }}.</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
            <div class="col-md-4">
                <a href="{{ route('penalties.index') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5">My Penalties</h3>
                            <p class="text-muted mb-0">View warnings and penalties issued to you.</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <h2 class="h5 text-muted mb-3">Performance</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <a href="{{ route('competence.index') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5">Personal Competence</h3>
                            <p class="text-muted mb-0">Vote for colleagues and see the votes you've received.</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('performance.index') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5">{{ auth()->user()->isAdmin() ? 'Performance Entries' : 'My Performance' }}</h3>
                            <p class="text-muted mb-0">
                                @if (auth()->user()->isAdmin())
                                    Enter monthly values and view computed scores.
                                @elseif (auth()->user()->isHod())
                                    View your own and your department's performance scores.
                                @else
                                    View your monthly performance scores.
                                @endif
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            @if (auth()->user()->isAdmin())
                <div class="col-md-4">
                    <a href="{{ route('admin.performance-settings.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Scoring Formula</h3>
                                <p class="text-muted mb-0">Customize inputs and score components.</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </section>

    @if (auth()->user()->isAdmin())
        <section class="mb-4">
            <h2 class="h5 text-muted mb-3">Administration</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <a href="{{ route('admin.users.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Manage Accounts</h3>
                                <p class="text-muted mb-0">Create and manage employee and admin accounts.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('admin.departments.index') }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h5">Departments</h3>
                                <p class="text-muted mb-0">Manage departments.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>
    @endif
@endsection

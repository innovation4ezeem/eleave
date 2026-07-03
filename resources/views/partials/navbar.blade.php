<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs(['leave.*', 'balances.*', 'admin.applications.*', 'admin.historical.*']) ? 'active' : '' }}" href="#" id="navLeaveDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        E-Leave
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navLeaveDropdown">
                        <li><a class="dropdown-item {{ request()->routeIs('leave.index') || request()->routeIs('leave.create') || request()->routeIs('leave.show') ? 'active' : '' }}" href="{{ route('leave.index') }}">My Leave</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('balances.*') ? 'active' : '' }}" href="{{ route('balances.index') }}">My Balances</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('leave.report') ? 'active' : '' }}" href="{{ route('leave.report') }}">Leave Report</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('penalties.index') ? 'active' : '' }}" href="{{ route('penalties.index') }}">My Penalties</a></li>
                        @if (auth()->user()->isAdminOrHod())
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.applications.*') ? 'active' : '' }}" href="{{ route('admin.applications.index') }}">Leave Applications</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.historical.*') ? 'active' : '' }}" href="{{ route('admin.historical.index') }}">Historical Leave</a></li>
                        @endif
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs(['competence.*', 'performance.*', 'admin.competence-categories.*', 'admin.competence-votes.*', 'admin.performance-settings.*', 'admin.performance-entries.*']) ? 'active' : '' }}" href="#" id="navPerformanceDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Performance
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navPerformanceDropdown">
                        <li><a class="dropdown-item {{ request()->routeIs('competence.*') ? 'active' : '' }}" href="{{ route('competence.index') }}">My Competence</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('performance.*') ? 'active' : '' }}" href="{{ route('performance.index') }}">{{ auth()->user()->isAdmin() ? 'Performance Entries' : 'My Performance' }}</a></li>
                        @if (auth()->user()->isAdmin())
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.performance-entries.*') ? 'active' : '' }}" href="{{ route('admin.performance-entries.index') }}">Manage Entries</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.performance-settings.*') ? 'active' : '' }}" href="{{ route('admin.performance-settings.index') }}">Scoring Formula</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.competence-categories.*') ? 'active' : '' }}" href="{{ route('admin.competence-categories.index') }}">Competence Categories</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.competence-votes.*') ? 'active' : '' }}" href="{{ route('admin.competence-votes.index') }}">Voting Log</a></li>
                        @endif
                    </ul>
                </li>

                @if (auth()->user()->isAdminOrHod())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs(['admin.users.*', 'admin.departments.*', 'admin.penalties.*', 'admin.entitlement-tiers.*', 'admin.leave-years.*']) ? 'active' : '' }}" href="#" id="navAdminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Administration
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navAdminDropdown">
                            <li><a class="dropdown-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.penalties.*') ? 'active' : '' }}" href="{{ route('admin.penalties.index') }}">Penalties</a></li>
                            @if (auth()->user()->isAdmin())
                                <li><a class="dropdown-item {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}" href="{{ route('admin.departments.index') }}">Departments</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('admin.entitlement-tiers.*') ? 'active' : '' }}" href="{{ route('admin.entitlement-tiers.index') }}">Entitlement Tiers</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('admin.leave-years.*') ? 'active' : '' }}" href="{{ route('admin.leave-years.index') }}">Leave Years</a></li>
                            @endif
                        </ul>
                    </li>
                @endif
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item d-flex align-items-center text-light me-3">
                    <a class="link-light text-decoration-none {{ request()->routeIs('profile.edit') ? 'fw-bold' : '' }}" href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a>
                    <span class="badge bg-secondary ms-2 text-uppercase">{{ auth()->user()->role }}</span>
                </li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

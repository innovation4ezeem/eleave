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
                        @if (auth()->user()->isAdmin())
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.applications.*') ? 'active' : '' }}" href="{{ route('admin.applications.index') }}">Leave Applications</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.historical.*') ? 'active' : '' }}" href="{{ route('admin.historical.index') }}">Historical Leave</a></li>
                        @endif
                    </ul>
                </li>

                @if (auth()->user()->isAdmin())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs(['admin.users.*', 'admin.departments.*']) ? 'active' : '' }}" href="#" id="navAdminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Administration
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navAdminDropdown">
                            <li><a class="dropdown-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}" href="{{ route('admin.departments.index') }}">Departments</a></li>
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

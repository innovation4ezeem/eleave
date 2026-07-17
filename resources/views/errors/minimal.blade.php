<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center text-center" style="min-height: 100vh;">
            <div class="col-12 col-md-6 col-lg-5">
                <h1 class="display-1 fw-bold text-secondary">@yield('code')</h1>
                <h2 class="mb-3">@yield('title')</h2>
                <p class="text-muted mb-4">@yield('message')</p>
                <a href="{{ url('/') }}" class="btn btn-primary">Go to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>

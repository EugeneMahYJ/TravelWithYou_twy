<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Travel With You Admin</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body class="admin-auth-body admin-error-body">
    <main class="admin-auth-card admin-error-card">
        <a class="admin-auth-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('assets/company-logo.png') }}" alt="Travel With You"><span>Admin</span></a>
        <p class="eyebrow accent">Error {{ $status }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a class="button button-primary button-full" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
    </main>
</body>
</html>

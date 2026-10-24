<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin sign in · Travel With You</title><link rel="stylesheet" href="{{ asset('css/site.css') }}"></head>
<body class="admin-auth-body">
    <main class="admin-auth-card">
        <a class="admin-auth-brand" href="{{ route('home') }}"><img src="{{ asset('assets/company-logo.png') }}" alt="Travel With You"><span>Admin</span></a>
        <p class="eyebrow accent">Team access</p>
        <h1>Sign in</h1>
        @if($adminStatusMessage)<p class="admin-auth-status" role="status">{{ $adminStatusMessage }}</p>@endif
        <form method="post" action="{{ route('admin.login.submit') }}">@csrf
            <label><span>Username</span><input name="username" value="{{ old('username', old('email')) }}" autocomplete="username" required autofocus>@error('username')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Password</span><input type="password" name="password" autocomplete="current-password" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror</label>
            <button class="button button-primary button-full" type="submit">Continue</button>
        </form>
        <a class="text-link admin-auth-back" href="{{ route('home') }}">← Back to website</a>
    </main>
</body>
</html>

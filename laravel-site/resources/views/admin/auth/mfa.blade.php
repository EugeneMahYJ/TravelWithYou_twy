<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify sign-in · Travel With You</title><link rel="stylesheet" href="{{ asset('css/site.css') }}"><script src="{{ asset('js/admin.js') }}" defer></script></head>
<body class="admin-auth-body" data-login-url="{{ route('admin.login') }}" data-idle-login-url="{{ route('admin.login', ['reason' => 'idle']) }}">
    @php($codeError = $errors->first('code'))
    <main class="admin-auth-card admin-mfa-card">
        <a class="admin-auth-brand" href="{{ route('home') }}"><img src="{{ asset('assets/company-logo.png') }}" alt="Travel With You"><span>Admin</span></a>
        <div class="admin-mfa-intro">
            <span class="admin-auth-shield" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
            <h1>Verify it's you</h1>
            <p>Enter the 6-digit code from your authenticator app.</p>
        </div>
        <form method="post" action="{{ route('admin.mfa.verify') }}" data-admin-code-form>@csrf
            @if($codeError)<div class="admin-auth-error" id="admin-auth-code-error" role="alert">{{ $codeError }}</div>@endif
            <label><span>Authentication code</span><input class="admin-code-input" id="admin-auth-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="000000" required autofocus data-admin-code @if($codeError) aria-invalid="true" aria-describedby="admin-auth-code-error" @endif></label>
            <p class="admin-code-helper">Codes refresh every 30 seconds.</p>
            @if($codeError)<p class="admin-code-failure-hint">If it keeps failing, make sure your phone's time is set automatically.</p>@endif
            <label class="admin-remember-device"><input type="checkbox" name="remember_device" value="1"> Remember this device for 30 days</label>
            <button class="button button-primary button-full" type="submit" data-admin-submitting-label="Verifying…">Verify and sign in</button>
        </form>
        <form class="admin-auth-back-form" method="post" action="{{ route('admin.logout') }}" data-admin-logout-form>@csrf<button class="admin-auth-secondary" type="submit">← Back to sign in</button></form>
    </main>
</body>
</html>

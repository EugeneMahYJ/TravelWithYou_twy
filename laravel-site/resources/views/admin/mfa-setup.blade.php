@extends('layouts.app')
@section('title', 'Set up admin verification — Travel With You')
@section('content')
@include('partials.page-head', ['kicker' => 'Team access · Step 2', 'heading' => 'Set up your authenticator', 'intro' => 'Add the secret below to an authenticator app, then enter its current 6-digit code.'])
<section class="wrap auth-wrap">
    <form class="form-card auth-card" method="post" action="{{ route('admin.mfa.setup.verify') }}">@csrf
        <div class="mfa-secret"><span>Account</span><strong>{{ auth()->user()->email }}</strong><span>Setup key</span><code>{{ $secret }}</code></div>
        <p class="fine-print">In your authenticator app, choose “enter setup key” and select time-based (TOTP). Keep this secret private.</p>
        <a class="text-link" href="{{ $uri }}">Open authenticator setup link</a>
        <label><span>6-digit verification code</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>@error('code')<small class="field-error">{{ $message }}</small>@enderror</label>
        <button class="button button-primary button-full" type="submit">Verify and secure admin access</button>
    </form>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="text-link text-button" type="submit">Sign out</button></form>
</section>
@endsection

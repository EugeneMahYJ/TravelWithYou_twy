@extends('layouts.app')
@section('title', 'Verify admin sign-in — Travel With You')
@section('content')
@include('partials.page-head', ['kicker' => 'Team access · Step 2', 'heading' => 'Verify it’s you', 'intro' => 'Enter the current 6-digit code from your authenticator app.'])
<section class="wrap auth-wrap">
    <form class="form-card auth-card" method="post" action="{{ route('admin.mfa.verify') }}">@csrf
        <label><span>6-digit verification code</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>@error('code')<small class="field-error">{{ $message }}</small>@enderror</label>
        <button class="button button-primary button-full" type="submit">Verify and sign in</button>
    </form>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="text-link text-button" type="submit">Cancel and sign out</button></form>
</section>
@endsection

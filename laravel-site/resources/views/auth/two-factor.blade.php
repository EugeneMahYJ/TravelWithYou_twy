@extends('layouts.app')
@section('title', 'Verify sign-in — Travel With You')
@section('content')
<x-auth-shell title="Check your email" sub="We sent a 6-digit sign-in code to {{ $maskedEmail }}. It expires in {{ $minutes }} minutes.">
    <form class="auth-form" method="post" action="{{ route('login.two-factor.verify') }}">@csrf
        <label><span>Sign-in code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>@error('code')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <button class="button button-primary button-full" type="submit">Verify and sign in</button>
    </form>
    <form class="auth-form" method="post" action="{{ route('login.two-factor.resend') }}">@csrf
        <button class="button button-ghost button-full" type="submit">Send a new code</button>
        <p class="auth-footnote"><a href="{{ route('login') }}">Back to log in</a></p>
    </form>
</x-auth-shell>
@endsection

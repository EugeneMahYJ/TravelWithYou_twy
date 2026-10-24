@extends('layouts.app')
@section('title', 'Reset password — Travel With You')
@section('content')
<x-auth-shell title="Reset password" sub="We will email you a reset link.">
    <form class="auth-form" method="post" action="{{ route('password.email') }}">@csrf
        <label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <button class="button button-primary button-full" type="submit">Send reset link</button>
        <p class="auth-footnote"><a href="{{ route('login') }}">Back to log in</a></p>
    </form>
</x-auth-shell>
@endsection

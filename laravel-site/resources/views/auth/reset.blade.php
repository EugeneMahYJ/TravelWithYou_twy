@extends('layouts.app')
@section('title', 'Reset password — TravelWithYou')
@section('content')
<x-auth-shell title="Reset password" sub="Choose a new password for your account.">
    <form class="auth-form" method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <label><span>Email</span><input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <label><span>New password</span><input type="password" name="password" autocomplete="new-password" required>@error('password')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <label><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <button class="button button-primary button-full" type="submit">Reset password</button>
    </form>
</x-auth-shell>
@endsection

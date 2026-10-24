@extends('layouts.app')
@section('title', $adminLogin ? 'Admin sign in — Travel With You' : 'Sign in — Travel With You')
@section('content')
@if($adminLogin)
    @include('partials.page-head', ['kicker' => 'Team access', 'heading' => 'Admin sign in', 'intro' => 'Sign in with an administrator account to manage the travel collection.'])
    <section class="wrap auth-wrap"><form class="form-card auth-card" method="post" action="{{ route('admin.login.submit') }}">@csrf
        <label><span>Email address</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Password</span><input type="password" name="password" autocomplete="current-password" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror</label>
        <button class="button button-primary button-full" type="submit">Sign in</button>
    </form></section>
@else
    <x-auth-shell title="Welcome back" sub="Log in to see your trips and saved packages.">
        <form class="auth-form" method="post" action="{{ route('login.submit') }}">@csrf
            <label><span>Email or username</span><input type="text" name="email" value="{{ old('email') }}" autocomplete="username" required>@error('email')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
            <label><span>Password</span><input type="password" name="password" autocomplete="current-password" required>@error('password')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
            <div class="auth-forgot"><a href="{{ route('password.request') }}">Forgot password?</a></div>
            <button class="button button-primary button-full" type="submit">Log in</button>
            <p class="auth-footnote">New here? <a href="{{ route('register') }}">Create an account</a></p>
        </form>
    </x-auth-shell>
@endif
@endsection

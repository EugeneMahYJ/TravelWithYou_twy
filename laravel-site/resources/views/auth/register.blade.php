@extends('layouts.app')
@section('title', 'Join TravelWithYou')
@section('content')
<x-auth-shell title="Join TravelWithYou" sub="Save trips, speed up checkout and get new-user deals.">
    <form class="auth-form" method="post" action="{{ route('register.submit') }}">@csrf
        <label><span>Full name</span><input name="name" value="{{ old('name') }}" autocomplete="name" required>@error('name')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <label><span>Phone</span><input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">@error('phone')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
        <label><span>Password</span><input type="password" name="password" autocomplete="new-password" data-credential-password required>@foreach($errors->get('password') as $message)<small class="field-error" role="alert">{{ $message }}</small>@endforeach</label>
        <label><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <button class="button button-primary button-full" type="submit">Create account</button>
        <p class="auth-footnote">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </form>
</x-auth-shell>
@endsection

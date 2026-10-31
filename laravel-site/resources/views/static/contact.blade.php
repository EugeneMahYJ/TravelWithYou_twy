@extends('layouts.app')
@section('title', 'Contact us — TravelWithYou')
@section('content')
<x-page-head kicker="Contact us" title="Leave us a message." />
<section class="wrap contact-layout contact-port">
    <form class="form-card contact-form" method="post" action="{{ route('contact.send') }}">@csrf
        @if($errors->any())
            <p role="alert" class="notice notice-error">Please add your name, a valid email and a message.</p>
        @endif
        <div class="contact-form-grid">
            <label><span>Name</span><input name="name" value="{{ old('name') }}" autocomplete="name" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        <label><span>Topic</span>
            <select name="subject" required>
                @foreach(['Booking enquiry', 'Existing booking', 'Group travel', 'Press'] as $topic)
                    <option value="{{ $topic }}" @selected(old('subject', 'Booking enquiry') === $topic)>{{ $topic }}</option>
                @endforeach
            </select>
            @error('subject')<small class="field-error">{{ $message }}</small>@enderror
        </label>
        <label><span>Message</span><textarea name="message" rows="6" required>{{ old('message') }}</textarea>@error('message')<small class="field-error">{{ $message }}</small>@enderror</label>
        <button class="button button-primary" type="submit">Send message</button>
    </form>
    <aside class="contact-details-card">
        @if($company['phone'])
            <p><span aria-hidden="true">☎</span><a href="tel:{{ $company['phone'] }}">{{ $company['phone'] }}</a></p>
        @endif
        @if($company['email'])
            <p><span aria-hidden="true">✉</span><a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a></p>
        @endif
        @if($company['address'])
            <p><span aria-hidden="true">📍</span>{!! nl2br(e($company['address'])) !!}</p>
        @endif
        <p class="contact-hours">{!! nl2br(e($company['hours'])) !!}</p>
    </aside>
</section>
@endsection

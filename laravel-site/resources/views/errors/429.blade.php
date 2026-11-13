@extends('layouts.app')
@section('title', 'Error 429 — Travel With You')
@section('robots', 'noindex')
@section('content')
<section class="wrap not-found-page">
    <svg aria-hidden="true" viewBox="0 0 48 48">
        <path d="M4 31h40l-6 9H12l-8-9Z"></path>
        <path d="M24 5v26m0-23 13 11H24M24 13 12 22h12"></path>
        <path d="M5 44c3-2 5-2 8 0s5 2 8 0 5-2 8 0 5 2 8 0 5-2 8 0"></path>
    </svg>
    <p class="eyebrow accent">Error 429</p>
    <h1>Slow down a little.</h1>
    <p>You have made a lot of requests in a short time. Please wait a minute, then try again.</p>
    <div>
        <a class="button button-primary" href="{{ route('home') }}">Back home</a>
        <a class="button button-ghost" href="{{ route('destinations') }}">Browse destinations</a>
    </div>
</section>
@endsection

@extends('layouts.app')
@section('title', 'Payment failed — TravelWithYou')
@section('content')
<section class="wrap payment-failed-page">
    <div class="payment-failed-icon" aria-hidden="true">×</div>
    <h1>Payment didn&apos;t go through.</h1>
    <p>Your card was declined and you have not been charged. Please check the details or try another payment method. Your cart is saved.</p>
    <div>
        <a class="button button-primary" href="{{ route('checkout') }}">Retry payment</a>
        <a class="button button-ghost" href="{{ route('contact') }}">Contact support</a>
    </div>
</section>
@endsection

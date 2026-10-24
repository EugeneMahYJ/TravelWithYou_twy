@extends('layouts.app')
@section('title', 'Booking confirmation — TravelWithYou')
@section('content')
@if($bookings->isEmpty())
    <section class="wrap confirmation-empty">
        <p class="font-display">No recent booking.</p>
        <a class="button button-primary" href="{{ route('account') }}">View my bookings</a>
    </section>
@else
    <section class="wrap confirmation-page">
        <header class="confirmation-heading">
            <div class="confirmation-check" aria-hidden="true">✓</div>
            <p class="eyebrow accent">Booking confirmed</p>
            <h1>Bon voyage.</h1>
            <div class="booking-reference">
                <span>Booking reference</span>
                <strong>{{ $orderReference }}</strong>
            </div>
            @if($bookings->contains(fn ($booking) => $booking->status === 'Pending payment'))
                <p class="pending-payment-note">Pending payment</p>
            @endif
        </header>
        <p class="notice notice-info" role="status">A confirmation email with your itinerary is on its way.</p>
        <section class="confirmation-card">
            <h2>Booking summary</h2>
            <ul class="confirmation-items">
                @foreach($bookings as $booking)
                    @php
                        $details = $booking->guest_details ?? [];
                        $bookingDetail = $details['booking_detail'] ?? [];
                        $rooms = collect($bookingDetail['rooms'] ?? []);
                        $roomText = $rooms->map(fn ($room, $index) => 'Room '.($index + 1).': '.($room['type'] ?? 'Room type pending'))->join(', ');
                    @endphp
                    <li>
                        <span>
                            <strong>{{ $booking->package->name }}</strong>
                            <small>{{ fmt_date($booking->departure_date) }} · {{ $booking->guests }} pax{{ $roomText ? ' · '.$roomText : '' }}</small>
                            @if(!empty($details['addons']))<small>Add-ons: {{ collect($details['addons'])->map(fn ($id) => collect(\App\Support\Pricing::ADDONS)->firstWhere('id', $id)['label'] ?? $id)->join(', ') }}</small>@endif
                        </span>
                        <b>{{ fmt_rm($booking->subtotal - $booking->discount + $booking->taxes) }}</b>
                    </li>
                @endforeach
            </ul>
            <div class="summary-line summary-total"><span>Total paid (incl. taxes)</span><strong>{{ fmt_rm($totalPaid) }}</strong></div>
        </section>
        <section class="confirmation-next">
            <h2>What happens next</h2>
            <ol>
                <li><span>01</span>Within 24 hours your travel specialist will email you to confirm traveller details.</li>
                <li><span>02</span>Your e-documents and packing guide arrive 60 days before departure.</li>
                <li><span>03</span>The balance, if any, is due 90 days before you travel.</li>
            </ol>
        </section>
        <div class="confirmation-actions">
            <a class="button button-primary" href="{{ route('confirmation.invoice', ['reference' => $orderReference]) }}">Download invoice</a>
            <a class="button button-ghost" href="{{ route('packages.pdf', ['package' => $bookings->first()->package->slug, 'download' => 1]) }}">Download brochure</a>
            <a class="button button-ghost" href="{{ route('account') }}">My bookings</a>
        </div>
    </section>
@endif
@endsection

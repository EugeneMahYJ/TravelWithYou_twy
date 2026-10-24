<article class="account-trip-card">
    <div class="account-trip-top">
        <span class="account-reference">{{ $booking->reference }}</span>
        <span class="booking-status {{ $completed ? 'is-completed' : 'is-upcoming' }}">{{ $completed ? 'Completed' : 'Upcoming' }}</span>
    </div>
    <div class="account-trip-main">
        <a class="account-trip-image" href="{{ route('packages.show', ['package' => $booking->package->slug]) }}">
            <img src="{{ asset_img($booking->package->image) }}" alt="{{ $booking->package->country }}">
        </a>
        <div class="account-trip-copy">
            <h3><a href="{{ route('packages.show', ['package' => $booking->package->slug]) }}">{{ $booking->package->name }}</a></h3>
            <p>{{ $booking->departure_date->format('d M Y') }} · {{ $booking->guests }} pax @if(data_get($booking->guest_details, 'cabin_class')) · {{ data_get($booking->guest_details, 'cabin_class') }} cabin @endif</p>
        </div>
        <strong class="account-trip-total">RM {{ number_format($booking->total) }}</strong>
    </div>
</article>

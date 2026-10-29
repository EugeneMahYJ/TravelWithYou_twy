@extends('layouts.app')
@section('title', isset($package) ? 'Booking — '.$package->name : 'Booking — Travel With You')
@section('content')
@if($state)
    @php
        $packageUrl = isset($package) ? route('packages.show', ['package' => $package->slug]) : route('destinations');
        $emptyStates = [
            'package-not-found' => [
                'title' => "We couldn't find that package.",
                'body' => 'The package may have been removed or the link is out of date.',
                'url' => route('destinations'),
                'cta' => 'Browse destinations',
            ],
            'departure-unavailable' => [
                'title' => "That departure isn't available.",
                'body' => 'The date in this link is no longer on sale. Choose another departure on the package page.',
                'url' => $packageUrl,
                'cta' => 'Choose a departure',
            ],
            'no-cabins' => [
                'title' => 'No room types on sale.',
                'body' => 'This ship has no rooms open for this sailing right now. Please check back soon or contact us.',
                'url' => $packageUrl,
                'cta' => 'Back to package',
            ],
        ];
        $empty = $emptyStates[$state];
    @endphp
    <section class="wrap booking-empty">
        <div class="booking-empty-icon" aria-hidden="true">!</div>
        <h1>{{ $empty['title'] }}</h1>
        <p>{{ $empty['body'] }}</p>
        <a class="button button-primary" href="{{ $empty['url'] }}">{{ $empty['cta'] }}</a>
    </section>
@else
    @php
        $bookingConfig = [
            'package' => [
                'slug' => $package->slug,
                'name' => $package->name,
                'type' => $package->type,
                'days' => $package->days,
                'priceFrom' => $package->price_from,
                'image' => $packageImage,
                'fares' => $package->fares ?? [],
            ],
            'date' => $date,
            'departurePrices' => $package->pricing['departure_prices'][$date] ?? [],
            'paxTiers' => \App\Support\Pricing::paxPriceTiers($package, $date),
            'cruise' => $cruise,
            'ship' => $ship ? ['name' => $ship->name] : null,
            'cabins' => $cabins,
            'deal' => $deal,
            'taxRate' => $taxRate,
            'maxPerRoom' => \App\Support\Pricing::MAX_PER_ROOM,
            'guestKinds' => $guestKinds,
            'addons' => $addons,
            'nations' => ['Malaysian', 'Singaporean', 'Indonesian', 'Thai', 'Chinese', 'Indian', 'Japanese', 'Korean', 'Australian', 'British', 'American', 'Canadian', 'German', 'French'],
            'packageUrl' => $packageUrl,
            'submitUrl' => $submitUrl,
            'editing' => $editing,
            'today' => today()->toDateString(),
            'oldInput' => $oldInput,
            'errors' => $errors,
        ];
    @endphp
    <section class="wrap booking-page" data-booking-root>
        <script type="application/json" data-booking-config>@json($bookingConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        <form class="booking-form" method="post" action="{{ $submitUrl }}" data-booking-form>
            @csrf
            <div data-booking-app></div>
            <noscript><p class="notice">Enable JavaScript to complete your booking.</p></noscript>
        </form>
    </section>
    <script type="module" src="{{ asset('js/booking.js') }}?v={{ filemtime(public_path('js/booking.js')) }}"></script>
@endif
@endsection

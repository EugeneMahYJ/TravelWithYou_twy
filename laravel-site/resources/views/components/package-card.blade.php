@props(['package', 'showCountry' => true, 'showHomeDetails' => false])

@php($isSaved = auth()->check() ? auth()->user()->favorites()->whereKey($package->id)->exists() : in_array($package->id, session('wishlist', [])))
@php($deal = \App\Support\Pricing::dealFor($package))
@php($unitPrice = \App\Support\Pricing::unitPrice($package, 1))
@if($showHomeDetails)
    @php($route = collect([$package->departure_port, $package->destination_port])->filter(fn ($port) => filled($port))->join(' → '))
    @php($nextDeparture = collect(\App\Support\Pricing::availableDepartureDates($package))->first(fn ($date) => \Illuminate\Support\Carbon::parse($date)->startOfDay()->greaterThanOrEqualTo(today())))
    @php($route = $route ?: 'See itinerary on trip page')
@endif

<article class="package-card">
    <div class="package-media">
        <a class="package-image" href="{{ route('packages.show', ['package' => $package->slug]) }}">
            <img src="{{ asset_img($package->image) }}" alt="{{ $package->country }} landscape" loading="lazy">
        </a>
        @if($deal)
            <span class="deal-tag">{{ $deal->discount_percent }}% off</span>
        @endif
        <form class="wishlist-form" method="post" action="{{ route('wishlist.toggle', ['package' => $package->id]) }}">@csrf
            <button type="submit" data-saved-label="Remove {{ $package->name }} from wishlist" data-unsaved-label="Add {{ $package->name }} to wishlist" aria-label="{{ $isSaved ? 'Remove '.$package->name.' from wishlist' : 'Add '.$package->name.' to wishlist' }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"></path></svg></button>
        </form>
    </div>
    <div class="package-copy">
        <div class="package-card-meta">
            <span>{{ $showCountry ? $package->country : $package->type }}</span>
            <span>{{ $package->days }} days{{ $package->ship ? ' · '.$package->ship : '' }}</span>
        </div>
        <h3><a href="{{ route('packages.show', ['package' => $package->slug]) }}">{{ $package->name }}</a></h3>
        <p>{{ $package->description }}</p>
        @if($showHomeDetails)
            <div class="package-home-details">
                <p><span>Route</span><strong>{{ $route }}</strong></p>
                <p><span>Next departure</span><strong>{{ $nextDeparture ? fmt_date($nextDeparture) : 'Dates to be announced' }}</strong></p>
            </div>
        @endif
        <div class="package-meta">
            <div class="package-price">
                <span class="package-price-label">{{ $showHomeDetails ? 'Starting price per person' : 'From' }}</span>
                <strong>{{ fmt_rm($unitPrice) }}</strong>
                @if($deal)
                    <del>{{ fmt_rm($package->price_from) }}</del>
                @endif
            </div>
            @if($package->reviews > 0)
                <x-stars :value="$package->rating" :count="$package->reviews" />
            @endif
        </div>
        <a class="button button-ghost package-card-button" href="{{ route('packages.show', ['package' => $package->slug]) }}">View Details</a>
    </div>
</article>

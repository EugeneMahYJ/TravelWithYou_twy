@extends('layouts.app')
@section('title', 'Deals — TravelWithYou')
@section('content')
@include('partials.page-head', ['kicker' => 'Deals', 'heading' => 'Savings with the small print up front.', 'intro' => 'Every offer shows what you pay, what you save and the terms, with no surprises at checkout.'])
<section class="wrap deals-search-section">
    <x-search-bar :options="$searchOptions" :action="route('deals')" cta="Search deals" :clear-url="route('deals')" />
</section>
<section class="wrap deals-content">
    <nav class="deal-tabs" aria-label="Deal types">
        @foreach(['All', 'New user', 'Limited-time', 'Early bird', 'Group discount'] as $tab)
            @php
                $tabQuery = request()->except(['tab', 'page']);
                if ($tab !== 'All') $tabQuery['tab'] = $tab;
            @endphp
            <a href="{{ route('deals', $tabQuery) }}" class="{{ $activeTab === $tab ? 'is-active' : '' }}" @if($activeTab === $tab) aria-current="page" @endif>{{ $tab }}</a>
        @endforeach
    </nav>
    @if($deals->isNotEmpty())
        <div class="deal-grid deal-grid-port">
            @foreach($deals as $deal)
                @php
                    $package = $deal->package;
                    $pax = $deal->type === 'Group discount' ? 6 : 2;
                    $salePrice = \App\Support\Pricing::unitPrice($package, $pax);
                    $departure = collect(\App\Support\Pricing::availableDepartureDates($package))->first();
                    $endsAt = $deal->expires_at?->toIso8601String()
                        ?? $deal->ends_at?->endOfDay()->toIso8601String();
                @endphp
                <article class="deal-card deal-card-port">
                    <div class="deal-card-image">
                        <img src="{{ asset_img($package->image) }}" alt="{{ $package->name }}" loading="lazy">
                        <span>{{ number_format((float) $deal->discount_percent, 0) }}% off</span>
                    </div>
                    <div class="deal-card-content">
                        <p class="deal-type">{{ $deal->type }}</p>
                        <h2>{{ $package->name }}</h2>
                        <p class="deal-meta">{{ $package->country }} · {{ $package->days }} days</p>
                        @if($endsAt)
                            <div class="deal-countdown" data-countdown data-ends-at="{{ $endsAt }}" role="timer" aria-live="off" aria-label="Offer countdown">
                                @foreach(['days' => 'days', 'hours' => 'hrs', 'minutes' => 'min', 'seconds' => 'sec'] as $unit => $short)
                                    <span><strong data-countdown-value="{{ $unit }}">00</strong><small>{{ $short }}</small></span>
                                @endforeach
                            </div>
                        @endif
                        <div class="deal-price">
                            <strong>{{ fmt_rm($salePrice) }}</strong>
                            <del>{{ fmt_rm($package->price_from) }}</del>
                            <small>per person</small>
                        </div>
                        <p class="deal-terms"><strong>Terms:</strong> {{ $deal->terms ?: $deal->description }}</p>
                        <div class="deal-actions">
                            @if($departure)
                                <form method="post" action="{{ route('cart.add', ['package' => $package->id]) }}">
                                    @csrf
                                    <input type="hidden" name="departure_date" value="{{ $departure }}">
                                    <input type="hidden" name="guests" value="{{ $pax }}">
                                    <button class="button button-primary" type="submit">Book Now</button>
                                </form>
                            @else
                                <button class="button button-primary" type="button" disabled>Book Now</button>
                            @endif
                            <a class="button button-ghost" href="{{ route('packages.show', ['package' => $package->slug]) }}">View Deal</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <p class="deals-empty">No deals match. Try clearing a filter.</p>
    @endif
</section>
<script src="{{ asset('js/countdown.js') }}" defer></script>
@endsection

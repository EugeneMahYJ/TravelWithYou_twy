@extends('layouts.app')
@section('title', $package->name.' — Travel With You')
@section('content')
@php
    $isSaved = auth()->check()
        ? auth()->user()->favorites()->whereKey($package->id)->exists()
        : in_array($package->id, session('wishlist', []));
    $availableDates = \App\Support\Pricing::availableDepartureDates($package);
    $selectedDate = $availableDates[0] ?? '';
    $selectedFare = $selectedDate !== '' ? \App\Support\Pricing::fareFor($package, $selectedDate) : null;
    $displayPrice = \App\Support\Pricing::unitPrice($package, 2, $selectedDate !== '' ? $selectedDate : null);
    $originalPrice = $selectedFare ? \App\Support\Pricing::fareUnitPrice($package, $selectedFare) : $package->price_from;
    $dailyPlan = $package->dailyPlan();
    $longDescription = $package->long_description ?: $package->details ?: $package->description;
    $filename = $package->slug.'-TravelWithYou.pdf';
@endphp
<div class="wrap package-breadcrumb">
    <p>
        <a href="{{ route('destinations') }}">Destinations</a>
        /
        @if($countryRecord)
            <a href="{{ route('destinations.country', ['country' => $countryRecord->slug]) }}">{{ $countryRecord->name }}</a>
        @else
            <a href="{{ route('destinations') }}">{{ $package->country }}</a>
        @endif
        / {{ $package->name }}
    </p>
</div>
<div class="wrap package-detail-layout">
    <main class="package-detail-main">
        <x-slideshow :slides="$gallery" :alt="$package->name" />
        <div class="package-detail-meta">
            <span class="package-type-chip">{{ $package->type }}</span>
            <span>{{ $package->days }} days</span>
            <span>{{ $package->departure_port }} → {{ $package->destination_port }}</span>
            @if($package->reviews > 0)
                <x-stars :value="$package->rating" :count="$package->reviews" />
            @endif
        </div>
        <h1 class="package-detail-title">{{ $package->name }}</h1>
        <p class="package-long-description">{{ $longDescription }}</p>

        <section class="package-detail-section">
            <h2 class="detail-subhead">Daily trip plan</h2>
            <ol class="daily-plan">
                @foreach($dailyPlan as $day)
                    <li>
                        <div class="daily-plan-number">Day<strong>{{ $loop->iteration }}</strong></div>
                        <div class="daily-plan-content">
                            <h3>{{ $day['place'] }}</h3>
                            @if($day['activities'] !== '')
                                <p>{{ $day['activities'] }}</p>
                            @endif
                            @if($day['transport'] !== '')
                                <span class="transport-chip">
                                    <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M3 14V5.5A2.5 2.5 0 0 1 5.5 3h9A2.5 2.5 0 0 1 17 5.5V14M3 10h14M6 16v1m8-1v1M5 14h10"></path><circle cx="6" cy="14" r="1"></circle><circle cx="14" cy="14" r="1"></circle></svg>
                                    {{ $day['transport'] }}
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        @if($ship)
            <section class="package-detail-section">
                <h2 class="detail-subhead">Ship room types · {{ $ship->name }}</h2>
                <div class="cabin-grid">
                    @foreach($ship->cabins as $cabin)
                        <article class="cabin-card">
                            <p class="cabin-size">{{ $cabin->size }}</p>
                            <h3>{{ $cabin->type }}</h3>
                            <p class="cabin-note">{{ $cabin->note }}</p>
                            <p class="cabin-price"><span>From</span> {{ fmt_rm(\App\Support\Pricing::cabinPrices($package)[$cabin->type] ?? $cabin->price_from) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($ship)
            <a class="sailing-link" href="{{ route('ships.show', ['ship' => $ship->slug]) }}">
                <img src="{{ asset_img($ship->image) }}" alt="" loading="lazy">
                <span><small>Sailing aboard</small><strong>{{ $ship->name }}</strong></span>
                <span class="sailing-arrow" aria-hidden="true">→</span>
            </a>
        @endif
    </main>

    <aside class="package-booking-aside">
        <div class="package-booking-card">
            @if($deal)
                <div class="package-deal-banner">
                    <span>{{ $deal->type }}: {{ $deal->discount_percent }}% off</span>
                    @if($deal->expires_at || $deal->ends_at)
                        <span class="deal-ends-soon">ends soon</span>
                    @endif
                </div>
            @endif
            <div class="package-booking-price">
                <span class="package-from-label">From</span>
                <strong data-current-price>{{ fmt_rm($displayPrice) }}</strong>
                <span class="package-per-person">/ person</span>
                <del data-original-price @if($displayPrice >= $originalPrice) hidden @endif>{{ fmt_rm($originalPrice) }}</del>
            </div>
            <label class="departure-select-label" for="package-departure">
                <span>Departure date</span>
                <select id="package-departure" data-package-departure data-booking-base="{{ url('/book/'.$package->slug) }}">
                    @foreach($availableDates as $date)
                        @php
                            $fare = \App\Support\Pricing::fareFor($package, $date);
                            $farePrice = $fare ? \App\Support\Pricing::fareUnitPrice($package, $fare) : $package->price_from;
                            $datePrice = \App\Support\Pricing::unitPrice($package, 2, $date);
                            $fareNote = $fare['note'] ?? '';
                        @endphp
                        <option
                            value="{{ $date }}"
                            data-price="{{ fmt_rm($datePrice) }}"
                            data-original="{{ fmt_rm($farePrice) }}"
                            data-note="{{ $fareNote }}"
                            @selected($date === $selectedDate)
                        >{{ fmt_date($date) }}@if($fare) — {{ fmt_rm($farePrice) }}@if($fare['bogo'] ?? false) pp (buy 1 free 1)@endif @endif</option>
                    @endforeach
                </select>
            </label>
            <p class="fare-note" data-fare-note @if(empty($selectedFare['note'])) hidden @endif>{{ $selectedFare['note'] ?? '' }}</p>
            <div class="package-booking-actions">
                <a class="button button-primary button-full" data-book-now href="{{ route('booking', ['id' => $package->slug, 'date' => $selectedDate]) }}">Book Now</a>
                <form data-wishlist-form method="post" action="{{ route('wishlist.toggle', ['package' => $package->id]) }}">
                    @csrf
                    <button class="button button-ghost button-full save-package-button" type="submit" data-saved-label="Saved" data-unsaved-label="Save" data-saved-aria-label="Remove {{ $package->name }} from wishlist" data-unsaved-aria-label="Add {{ $package->name }} to wishlist" aria-label="{{ $isSaved ? 'Remove '.$package->name.' from wishlist' : 'Add '.$package->name.' to wishlist' }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}">
                        <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M17.2 4.4a4.5 4.5 0 0 0-6.4 0L10 5.2l-.8-.8a4.5 4.5 0 0 0-6.4 6.4l.9.9L10 18l6.3-6.3.9-.9a4.5 4.5 0 0 0 0-6.4Z"></path></svg>
                        {{ $isSaved ? 'Saved' : 'Save' }}
                    </button>
                </form>
            </div>
            <a class="ask-us-link" href="{{ route('contact') }}">Ask us</a>
        </div>
        <section class="package-glance" aria-labelledby="package-glance-title">
            <h2 id="package-glance-title">Trip at a glance</h2>
            <dl>
                @if($package->days)
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ $package->days }} days</dd>
                    </div>
                @endif
                @if($package->type)
                    <div>
                        <dt>Travel style</dt>
                        <dd>{{ $package->type }}</dd>
                    </div>
                @endif
                @if($package->departure_port || $package->destination_port)
                    <div>
                        <dt>Route</dt>
                        <dd>{{ $package->departure_port ?: $package->country }} → {{ $package->destination_port ?: $package->country }}</dd>
                    </div>
                @endif
                @if(count($availableDates) > 0)
                    <div>
                        <dt>Departures</dt>
                        <dd>{{ count($availableDates) }} available {{ count($availableDates) === 1 ? 'date' : 'dates' }}</dd>
                    </div>
                @endif
            </dl>
        </section>
        @if(!empty($highlights))
            <section class="package-glance package-highlights" aria-labelledby="package-highlights-title">
                <h2 id="package-highlights-title">Highlights</h2>
                <ul class="detail-check-grid">
                    @foreach($highlights as $highlight)
                        <li>
                            <svg class="check-icon check-vermilion" aria-hidden="true" viewBox="0 0 20 20"><path d="m4 10 4 4 8-9"></path></svg>
                            <span>{{ is_array($highlight) ? ($highlight['name'] ?? $highlight['title'] ?? implode(' · ', array_filter($highlight, 'is_scalar'))) : $highlight }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <div class="detail-inclusions">
            <section>
                <h2 class="detail-subhead">Included</h2>
                <ul class="inclusion-list">
                    @foreach($package->included() as $inclusion)
                        <li><svg class="check-icon check-sea" aria-hidden="true" viewBox="0 0 20 20"><path d="m4 10 4 4 8-9"></path></svg><span>{{ $inclusion }}</span></li>
                    @endforeach
                </ul>
            </section>
            <section>
                <h2 class="detail-subhead">Not included</h2>
                <ul class="inclusion-list excluded-list">
                    @foreach($package->excluded() as $exclusion)
                        <li><span class="exclude-icon" aria-hidden="true">×</span><span>{{ $exclusion }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>
        <section class="package-pdf-card">
            <div class="package-pdf-heading">
                <svg aria-hidden="true" viewBox="0 0 32 32"><path d="M8 3h11l7 7v19H8zM19 3v8h7M12 18h10M12 22h10"></path></svg>
                <div><h2>Package PDF</h2><p>{{ $filename }}</p></div>
            </div>
            <div class="package-pdf-actions">
                <button class="button button-ghost" type="button" data-pdf-open aria-haspopup="dialog">
                    <svg aria-hidden="true" viewBox="0 0 20 20"><circle cx="8.5" cy="8.5" r="5.5"></circle><path d="m13 13 4 4"></path></svg>
                    Preview
                </button>
                <a class="button button-primary" href="{{ route('packages.pdf', ['package' => $package->slug, 'download' => 1]) }}">
                    <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M10 2v11m-4-4 4 4 4-4M3 15v3h14v-3"></path></svg>
                    Download
                </a>
            </div>
        </section>
    </aside>
</div>

<div class="package-pdf-modal" data-pdf-modal hidden>
    <section class="package-pdf-dialog" role="dialog" aria-modal="true" aria-labelledby="pdf-dialog-title" tabindex="-1">
        <header>
            <h2 id="pdf-dialog-title">{{ $filename }}</h2>
            <div>
                <a class="button button-primary button-small" href="{{ route('packages.pdf', ['package' => $package->slug, 'download' => 1]) }}">Download</a>
                <button type="button" class="pdf-close-button" aria-label="Close preview" data-pdf-close>×</button>
            </div>
        </header>
        <iframe data-pdf-src="{{ route('packages.pdf', ['package' => $package->slug]) }}" title="Package PDF preview"></iframe>
    </section>
</div>

@if($related->isNotEmpty())
    <section class="wrap section-block package-more-section">
        <x-section-title number="+" :title="'More in '.$package->country" />
        <div class="package-grid">
            @foreach($related as $relatedPackage)
                <x-package-card :package="$relatedPackage" />
            @endforeach
        </div>
    </section>
@endif
@endsection

@extends('layouts.app')
@section('title', $selectedShip ? $selectedShip->name.' — Ships' : 'Ships — TravelWithYou')
@section('content')
@if($selectedShip)
    <section class="ship-detail-hero" style="--hero-image:url('{{ asset_img($selectedShip->image) }}')">
        <div class="detail-hero-shade"></div>
        <div class="wrap ship-detail-hero-copy">
            <p class="eyebrow"><a href="{{ route('ships') }}">Cruise</a> / {{ $selectedShip->class_name }}</p>
            <h1>{{ $selectedShip->name }}</h1>
            <div class="ship-detail-meta">
                <x-stars :value="$selectedShip->rating" />
                <span>{{ number_format($selectedShip->capacity) }} guests</span>
                <span>{{ number_format($selectedShip->crew) }} crew</span>
                <span>Built {{ $selectedShip->year }}</span>
            </div>
        </div>
    </section>
    <section class="wrap ship-detail-content">
        <div class="ship-tabs" role="tablist" aria-label="{{ $selectedShip->name }} information">
            @foreach($tabs as $tab)
                <a id="ship-tab-{{ \Illuminate\Support\Str::slug($tab) }}"
                   href="{{ route('ships.show', ['ship' => $selectedShip->slug, 'tab' => $tab]) }}"
                   role="tab"
                   aria-controls="ship-panel-{{ \Illuminate\Support\Str::slug($tab) }}"
                   aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                   tabindex="{{ $activeTab === $tab ? '0' : '-1' }}"
                   data-ship-tab="{{ $tab }}"
                   class="{{ $activeTab === $tab ? 'is-active' : '' }}">{{ $tab }}</a>
            @endforeach
        </div>
        <div class="ship-tab-panels">
            <section id="ship-panel-overview" role="tabpanel" aria-labelledby="ship-tab-overview" data-ship-panel="Overview" @if($activeTab !== 'Overview') hidden @endif>
                <div class="ship-overview">
                    <p>{{ $selectedShip->overview }}</p>
                    <dl>
                        <div><dt>Class</dt><dd>{{ $selectedShip->class_name }}</dd></div>
                        <div><dt>Capacity</dt><dd>{{ number_format($selectedShip->capacity) }} guests</dd></div>
                        <div><dt>Crew</dt><dd>{{ number_format($selectedShip->crew) }}</dd></div>
                        <div><dt>Launched</dt><dd>{{ $selectedShip->year }}</dd></div>
                    </dl>
                </div>
            </section>
            <section id="ship-panel-gallery" role="tabpanel" aria-labelledby="ship-tab-gallery" data-ship-panel="Gallery" @if($activeTab !== 'Gallery') hidden @endif>
                <div class="ship-gallery">
                    @foreach($selectedShip->gallery ?? [] as $index => $media)
                        @php($isVideo = is_string($media) && str_starts_with($media, 'video:'))
                        <figure class="{{ $index === 0 ? 'is-featured' : '' }}">
                            @if($isVideo)
                                @php($videoPath = substr($media, 6))
                                <video controls playsinline preload="metadata" poster="{{ asset_img($selectedShip->image) }}" aria-label="{{ $selectedShip->name }} video">
                                    <source src="{{ asset('assets/'.(str_contains($videoPath, '/') ? $videoPath : 'video/'.$videoPath)) }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            @else
                                <img src="{{ asset_img($media) }}" alt="{{ $selectedShip->name }} view {{ $index + 1 }}" loading="lazy">
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
            <section id="ship-panel-deck-plans" role="tabpanel" aria-labelledby="ship-tab-deck-plans" data-ship-panel="Deck plans" @if($activeTab !== 'Deck plans') hidden @endif>
                <div class="ship-deck-list">
                    @foreach($selectedShip->decks ?? [] as $index => $deck)
                        <article class="ship-deck-row">
                            <div><span>{{ $deck['n'] ?? '' }}</span><h2>{{ $deck['label'] ?? '' }}</h2></div>
                            <div class="ship-deck-bar" style="width: {{ 100 - $index * 8 }}%"></div>
                            <p>{{ $deck['items'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
            <section id="ship-panel-amenities" role="tabpanel" aria-labelledby="ship-tab-amenities" data-ship-panel="Amenities" @if($activeTab !== 'Amenities') hidden @endif>
                <ul class="ship-amenities">
                    @foreach($selectedShip->amenities ?? [] as $amenity)
                        <li><span aria-hidden="true">⚓</span>{{ $amenity }}</li>
                    @endforeach
                </ul>
            </section>
            <section id="ship-panel-cabins" role="tabpanel" aria-labelledby="ship-tab-cabins" data-ship-panel="Cabins" @if($activeTab !== 'Cabins') hidden @endif>
                <div class="ship-cabins">
                    @foreach($selectedShip->cabins as $cabin)
                        <article>
                            <span class="ship-cabin-size">{{ $cabin->size }}</span>
                            <h2>{{ $cabin->type }}</h2>
                            <p>{{ $cabin->note }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
            <section id="ship-panel-recent-cruises" role="tabpanel" aria-labelledby="ship-tab-recent-cruises" data-ship-panel="Recent cruises" @if($activeTab !== 'Recent cruises') hidden @endif>
                <div class="ship-cruises">
                    @forelse($recentCruises as $package)
                        <article>
                            <div><h2>{{ $package->name }}</h2><span>{{ $package->country }}</span></div>
                            <div><small>Route</small>{{ $package->departure_port }} → {{ $package->destination_port }}</div>
                            <div><small>Departs</small>{{ $package->departures ? \Illuminate\Support\Carbon::parse($package->departures[0])->format('M Y') : 'Dates to be announced' }}</div>
                            <a class="button button-primary" href="{{ route('packages.show', ['package' => $package->slug]) }}">Book now</a>
                        </article>
                    @empty
                        <p>No recent cruises are listed for this ship.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
    <script src="{{ asset('js/ship-tabs.js') }}" defer></script>
@else
    @include('partials.page-head', ['kicker' => 'Ships', 'heading' => 'Choose the cruise, then the sea.', 'intro' => 'From boutique island-hoppers to ice-strengthened expedition vessels.'])
    <section class="wrap ship-list">
        <div class="ship-list-grid">
            @foreach($ships as $ship)
                <a class="ship-list-card" href="{{ route('ships.show', ['ship' => $ship->slug]) }}">
                    <div class="ship-list-image"><img src="{{ asset_img($ship->image) }}" alt="{{ $ship->name }}" loading="lazy"></div>
                    <div class="ship-list-copy">
                        <div class="ship-list-heading"><h2>{{ $ship->name }}</h2><x-stars :value="$ship->rating" /></div>
                        <div class="ship-list-facts">
                            <div><small>Capacity</small><span>{{ number_format($ship->capacity) }} guests</span></div>
                            <div><small>Class</small><span>{{ $ship->class_name }}</span></div>
                            <div><small>Built</small><span>{{ $ship->year }}</span></div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
@endsection

@extends('layouts.app')

@section('title', 'Travel With You — thoughtful journeys at your own pace')

@section('content')
<section class="hero" aria-label="Featured journeys" aria-roledescription="carousel" data-carousel>
    @foreach($slides as $index => $slide)
        <div class="hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-slide="{{ $index }}" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
            @if(!empty($slide['video']))
                <video class="hero-video" poster="{{ asset_img($slide['image']) }}" muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1">
                    <source src="{{ $slide['video'] }}" type="video/mp4">
                </video>
                <div class="hero-video-shade" aria-hidden="true"></div>
            @else
                <img class="hero-image {{ $index === 0 ? 'kenburns' : '' }}" src="{{ asset_img($slide['image']) }}" alt="">
            @endif
            <div class="wrap hero-copy fade-up">
                <p class="eyebrow hero-kicker">{{ $slide['kicker'] }}</p>
                <h1>{{ $slide['title'] }}</h1>
                <p>{{ $slide['text'] }}</p>
                <div class="hero-actions"><a class="button button-primary" href="{{ $slide['url'] }}">{{ $slide['label'] }} <span aria-hidden="true">→</span></a><a class="button button-outline-light" href="{{ $slide['secondary_url'] ?? route('deals') }}">{{ $slide['secondary_label'] ?? 'All deals' }}</a></div>
            </div>
        </div>
    @endforeach
    <div class="hero-shade"></div>
    <button class="hero-arrow hero-prev" type="button" aria-label="Previous offer">←</button>
    <button class="hero-arrow hero-next" type="button" aria-label="Next offer">→</button>
    <div class="hero-dots" role="group" aria-label="Choose offer">
        @foreach($slides as $index => $slide)<button type="button" aria-label="Show offer {{ $index + 1 }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}" data-slide-to="{{ $index }}"></button>@endforeach
        <button class="hero-pause" type="button" aria-label="Pause slideshow" aria-pressed="false" data-carousel-pause>Pause</button>
    </div>
</section>

<div class="wrap search-wrap">
    <x-search-bar :options="$searchOptions" :clear-url="route('home')" :clear-in-place="true" />
</div>

<section class="wrap section-block">
    <x-section-title number="01" title="Most popular trips"><a class="text-link" href="{{ route('destinations') }}">All destinations →</a></x-section-title>
    <div class="trend-row"><span class="eyebrow">Trending searches</span>
        <a href="{{ route('search', ['q' => 'sakura']) }}">Cherry blossom</a>
        <a href="{{ route('search', ['q' => 'Mediterranean']) }}">Mediterranean cruise</a>
        <a href="{{ route('search', ['q' => 'Wildlife']) }}">Safari</a>
        <a href="{{ route('search', ['q' => 'Beach', 'price' => '2-3.5']) }}">Beach under RM 3,500</a>
        <a href="{{ route('search', ['q' => 'aurora']) }}">Northern lights</a>
        <a href="{{ route('search', ['q' => 'Greece']) }}">Greek islands</a>
    </div>
    <div class="package-grid">@foreach($featured as $package)<x-package-card :package="$package" />@endforeach</div>
</section>

<section class="wrap section-block">
    <x-section-title number="02" title="Where to next"><a class="text-link" href="{{ route('destinations') }}">All destinations →</a></x-section-title>
    <div class="destination-grid destination-mosaic">
        @foreach($destinations as $index => $destination)
            <a class="destination-tile destination-tile-{{ $index + 1 }}" href="{{ route('destinations.country', ['country' => $destination->slug]) }}">
                <img src="{{ asset_img($destination->image) }}" alt="{{ $destination->name }}" loading="lazy"><span>{{ $destination->region }}</span><strong>{{ $destination->name }}</strong>
            </a>
        @endforeach
    </div>
</section>

<section class="fleet-band">
    <div class="wrap fleet-inner">
        <div class="fleet-intro">
            <p class="eyebrow">The fleet</p>
            <h2>Different ships, Different characters.</h2>
            <p>From a 780-guest expedition vessel to a 2,400-guest Mediterranean flagship, there is a deck plan for every kind of traveller.</p>
            <a class="button button-outline-light" href="{{ route('ships') }}">Explore Ships <span aria-hidden="true">→</span></a>
        </div>
        <div class="fleet-grid">
            @foreach($ships as $ship)
                <a class="fleet-card" href="{{ route('ships.show', ['ship' => $ship->slug]) }}">
                    <span class="fleet-image"><img src="{{ asset_img($ship->image) }}" alt="{{ $ship->name }}" loading="lazy"></span>
                    <span class="fleet-name">{{ $ship->name }}</span>
                    <span class="fleet-class">{{ $ship->class_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="wrap section-block">
    <x-section-title number="03" title="Travel guides"><a class="text-link" href="{{ route('blog') }}">All guides →</a></x-section-title>
    <div class="journal-grid journal-home-grid">
        @foreach($journalPosts as $post)
            <a class="guide-card" href="{{ route('blog.show', ['slug' => $post->slug]) }}">
                <span class="guide-image"><img src="{{ asset_img($post->image) }}" alt="{{ $post->title }}" loading="lazy"></span>
                <span class="guide-meta">{{ $post->category }} · {{ $post->read_time }}</span>
                <span class="guide-title">{{ $post->title }}</span>
            </a>
        @endforeach
    </div>
</section>
@endsection

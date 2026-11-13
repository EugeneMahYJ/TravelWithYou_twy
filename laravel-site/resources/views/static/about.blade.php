@extends('layouts.app')
@section('title', 'About — Travel With You')
@section('content')
@php
    $sections = [
        ['Your Trusted Partner for Travel and Tours in Malaysia', 'Established in early 2018, Travel With You Sdn Bhd is built on a solid foundation of over 15 years of expertise in the travel and tourism industry. Our mission is to deliver unparalleled service and professional guidance for both local and international travel planning, ensuring every journey is seamless and memorable.'],
        ['Our Services', 'We offer an extensive range of travel services designed to cater to diverse needs. These include MICE Tours (Meetings, Incentives, Conferences, and Events), group tours, personalized free-and-easy packages, ground-only tours, hotel and resort bookings, airport transfers, and exciting travel activities. Additionally, our team provides valuable insights and expert tips for destinations across Asia, Europe, and America, helping clients enjoy well-prepared and enriching journeys.'],
        ['Tailored Tour Packages', 'Our thoughtfully curated tour packages are crafted to meet market demand while showcasing the best of local and international destinations. We aim to create unforgettable travel experiences tailored to suit the unique interests and preferences of each client.'],
        ['Our Team', 'Our team represents a vibrant mix of cultures, reflecting Malaysia’s rich diversity. This multicultural foundation not only strengthens our relationships with loyal clients but also inspires new travelers to join us in discovering the wonders of the world. Together, we are dedicated to making every journey extraordinary.'],
    ];

    $imageChoices = [
        'assets/package-photo-18.jpg',
        'assets/package-photo-24.jpg',
        'assets/package-photo-34.jpg',
        'assets/boat-and-two-men.jpg',
        'assets/fern.jpg',
        'assets/1559599746-8823b38544c6.jpg',
        'assets/1558870832-c8db4b5b47d1.jpg',
        'assets/1563245372-f21724e3856d.jpg',
        'assets/1617170788899-ef9587d6e63f.jpg',
        'assets/1624253321171-1be53e12f5f4.jpg',
        'assets/1704314838317-460424fd6688.jpg',
        'assets/1583844056361-4418a8f2a985.jpg',
    ];

    $aboutPhotos = collect($imageChoices)->shuffle()->take(4)->values()->all();
@endphp
<header class="about-hero paper-grain">
    <div class="wrap about-hero-inner">
        <p class="eyebrow accent">About Us</p>
        <h1>About Us</h1>
        <span class="about-hero-mark" aria-hidden="true"></span>
    </div>
</header>
<div class="wrap about-port">
    <div class="about-sections">
        @foreach($sections as $index => [$title, $body])
            <article class="about-section {{ in_array($index, [0, 2], true) ? 'about-section-reversed' : '' }} {{ $index < 3 ? 'about-section-with-photo about-section-photo-'.($index + 1) : '' }} {{ $index === 0 ? 'about-section-featured' : '' }} {{ $index === 3 ? 'about-team' : '' }}">
                <div class="about-section-copy">
                    <span class="section-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $body }}</p>
                </div>
                @if($index < 3)
                    @php $storyPhoto = $aboutPhotos[$index] ?? $imageChoices[0]; @endphp
                    <div class="about-story-photo {{ $index === 1 ? 'about-story-photo-ratio-16-9' : 'about-story-photo-ratio-4-3' }}" role="img" aria-label="Photo space for {{ $title }}" style="background-image: url('{{ asset($storyPhoto) }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
                @endif
                @if($index === 3)
                    @php $teamPhoto = $aboutPhotos[3] ?? $imageChoices[0]; @endphp
                    <div class="about-team-photo about-team-photo-ratio-4-3 about-team-photo-empty" role="img" aria-label="Our Team photo space" style="background-image: url('{{ asset($teamPhoto) }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'About — TravelWithYou')
@section('content')
@php
    $sections = [
        ['Your Trusted Partner for Travel and Tours in Malaysia', 'Established in early 2018, Travel With You Sdn Bhd is built on a solid foundation of over 15 years of expertise in the travel and tourism industry. Our mission is to deliver unparalleled service and professional guidance for both local and international travel planning, ensuring every journey is seamless and memorable.'],
        ['Our Services', 'We offer an extensive range of travel services designed to cater to diverse needs. These include MICE Tours (Meetings, Incentives, Conferences, and Events), group tours, personalized free-and-easy packages, ground-only tours, hotel and resort bookings, airport transfers, and exciting travel activities. Additionally, our team provides valuable insights and expert tips for destinations across Asia, Europe, and America, helping clients enjoy well-prepared and enriching journeys.'],
        ['Tailored Tour Packages', 'Our thoughtfully curated tour packages are crafted to meet market demand while showcasing the best of local and international destinations. We aim to create unforgettable travel experiences tailored to suit the unique interests and preferences of each client.'],
        ['Our Team', 'Our team represents a vibrant mix of cultures, reflecting Malaysia’s rich diversity. This multicultural foundation not only strengthens our relationships with loyal clients but also inspires new travelers to join us in discovering the wonders of the world. Together, we are dedicated to making every journey extraordinary.'],
    ];
    $team = collect(config('team.members', []));
    $featuredRoles = ['Position 1', 'Position 2'];
    $featuredTeam = collect($featuredRoles)->map(function (string $role) use ($team): array {
        $member = $team->first(fn (array $candidate) => ($candidate['role'] ?? '') === $role);

        return [
            'name' => $member['name'] ?? $role,
            'role' => $role,
            'image' => $member['image'] ?? null,
        ];
    });
    $otherTeam = $team->reject(fn (array $member) => in_array($member['role'] ?? '', $featuredRoles, true));
@endphp
<header class="about-hero paper-grain">
    <div class="wrap about-hero-inner">
        <p class="eyebrow accent">About Us</p>
        <h1>About Us</h1>
        <span class="about-hero-mark" aria-hidden="true"></span>
    </div>
</header>
<main class="wrap about-port">
    <div class="about-sections">
        @foreach($sections as $index => [$title, $body])
            <article class="about-section {{ in_array($index, [0, 2], true) ? 'about-section-reversed' : '' }} {{ $index < 3 ? 'about-section-with-photo about-section-photo-'.($index + 1) : '' }} {{ $index === 0 ? 'about-section-featured' : '' }} {{ $index === 3 ? 'about-team' : '' }}">
                <div class="about-section-copy">
                    <span class="section-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $body }}</p>
                </div>
                @if($index < 3)
                    <div class="about-story-photo {{ $index === 1 ? 'about-story-photo-ratio-16-9' : 'about-story-photo-ratio-4-3' }}" role="img" aria-label="Photo space for {{ $title }}"></div>
                @endif
                @if($index === 3)
                    <div class="about-team-grid">
                        @foreach($featuredTeam as $member)
                            <article class="about-team-card">
                                @if(!empty($member['image']))
                                    <img class="about-team-photo about-team-photo-ratio-3-4" src="{{ asset_img($member['image']) }}" alt="{{ $member['name'] }}" loading="lazy">
                                @else
                                    <div class="about-team-photo about-team-photo-ratio-3-4 about-team-photo-empty" role="img" aria-label="{{ $member['role'] }} photo space"></div>
                                @endif
                                <h3>{{ $member['name'] }}</h3>
                                <p>{{ $member['role'] }}</p>
                            </article>
                        @endforeach
                        @foreach($otherTeam as $member)
                            <article class="about-team-card">
                                @if(!empty($member['image']))
                                    <img class="about-team-photo about-team-photo-ratio-3-4" src="{{ asset_img($member['image']) }}" alt="{{ $member['name'] }}" loading="lazy">
                                @else
                                    <div class="about-team-photo about-team-photo-ratio-3-4 about-team-photo-empty" role="img" aria-label="{{ $member['role'] }} photo space"></div>
                                @endif
                                <h3>{{ $member['name'] }}</h3>
                                <p>{{ $member['role'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</main>
@endsection

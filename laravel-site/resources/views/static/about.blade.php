@extends('layouts.app')
@section('title', 'About — TravelWithYou')
@section('content')
<x-page-head kicker="About" title="Planned by people who have been there." lead="We are a travel company of 140 specialists, with one rule: never sell a trip we would not take." />
@php
    $sections = [
        ['Your Trusted Partner for Travel and Tours in Malaysia', 'Established in early 2018, Travel With You Sdn Bhd is built on a solid foundation of over 15 years of expertise in the travel and tourism industry. Our mission is to deliver unparalleled service and professional guidance for both local and international travel planning, ensuring every journey is seamless and memorable.'],
        ['Mission & Vision', 'We want travel to be unhurried, honest about its costs and good for the places it touches. Our vision is a world where a well-planned trip is within reach of anyone curious enough to book one.'],
        ['Our Services', 'We offer an extensive range of travel services designed to cater to diverse needs. These include MICE Tours (Meetings, Incentives, Conferences, and Events), group tours, personalized free-and-easy packages, ground-only tours, hotel and resort bookings, airport transfers, and exciting travel activities. Additionally, our team provides valuable insights and expert tips for destinations across Asia, Europe, and America, helping clients enjoy well-prepared and enriching journeys.'],
        ['Our Team', 'Our team represents a vibrant mix of cultures, reflecting Malaysia’s rich diversity. This multicultural foundation not only strengthens our relationships with loyal clients but also inspires new travelers to join us in discovering the wonders of the world. Together, we are dedicated to making every journey extraordinary.'],
        ['Awards & Certifications', 'World Travel Awards: Best Tour Operator, Europe (2024). Certified B Corporation since 2022. Member of ATOL and the Cruise Lines International Association.'],
        ['Press / Media', 'For interviews, images and fact sheets, contact press@travelwithyou.com. Our press kit is updated each quarter.'],
    ];
    $team = config('team.members', []);
@endphp
<section class="wrap prose-section about-port">
    <div class="about-sections">
        @foreach($sections as $index => [$title, $body])
            <article>
                <span class="section-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                <h2>{{ $title }}</h2>
                <p>{{ $body }}</p>
            </article>
        @endforeach
    </div>
    @if(count($team))
        <section class="about-team">
            <h2>Team</h2>
            <div class="about-team-grid">
                @foreach($team as $member)
                    <article>
                        @if(!empty($member['image']))
                            <img src="{{ asset_img($member['image']) }}" alt="{{ $member['name'] }}" loading="lazy">
                        @endif
                        <h3>{{ $member['name'] }}</h3>
                        <p>{{ $member['role'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</section>
@endsection

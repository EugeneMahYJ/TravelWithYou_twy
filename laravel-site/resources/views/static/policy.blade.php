@extends('layouts.app')
@php
    // Policy content must be reviewed by legal counsel before launch.
    $policies = [
        'terms' => [
            'title' => 'Terms & Conditions',
            'items' => [
                ['Booking contract', 'A booking is confirmed when we issue a reference number and receive your deposit. Prices are per person in Malaysian ringgit (RM) and include the services listed on each package page.'],
                ['Changes by you', 'Date and traveller changes are free up to 60 days before departure, subject to availability. Later changes may incur supplier fees.'],
                ['Changes by us', 'We may adjust itineraries for safety, weather or supplier reasons. If a change is significant you can accept it, choose an alternative or receive a full refund.'],
                ['Liability', 'We are responsible for the proper performance of the services we sell. We are not liable for events outside our reasonable control.'],
            ],
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'items' => [
                ['What we collect', 'Contact details, passport information needed to book, payment tokens and the preferences you share with us.'],
                ['How we use it', 'To process bookings, provide support, send requested updates and meet legal requirements. We never sell personal data.'],
                ['Sharing', 'We share only what suppliers need to deliver your trip: airlines, hotels and cruise lines, under data-protection agreements.'],
                ['Your rights', 'You can request access, correction or deletion at any time at privacy@travelwithyou.com.'],
            ],
        ],
        'cancellation' => [
            'title' => 'Cancellation & Refund Policy',
            'items' => [
                ['90 days or more', 'Full refund of all payments, less a RM 50 administration fee per booking.'],
                ['89 to 45 days', '30% of the total package price is retained. The remainder is refunded.'],
                ['44 days or fewer', 'Non-refundable. We recommend travel insurance, available as an add-on.'],
                ['If we cancel', 'You receive a full refund within 14 days or may transfer to another departure at no cost.'],
            ],
        ],
    ];
    $currentPolicy = $policies[$policy];
@endphp
@section('title', $currentPolicy['title'].' — TravelWithYou')
@section('content')
<x-page-head kicker="Legal" :title="$currentPolicy['title']" :lead="'Last updated '.\Illuminate\Support\Carbon::parse(config('company.legal_updated'))->format('j F Y').'.'" />
<section class="wrap policy-sections">
    @foreach($currentPolicy['items'] as [$heading, $body])
        <article>
            <h2><span>{{ $loop->iteration }}.</span>{{ $heading }}</h2>
            <p>{{ $body }}</p>
        </article>
    @endforeach
</section>
@endsection

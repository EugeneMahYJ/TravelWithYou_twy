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
            'nations' => explode('|', 'Afghan|Albanian|Algerian|American|Andorran|Angolan|Antiguan and Barbudan|Argentine|Armenian|Australian|Austrian|Azerbaijani|Bahamian|Bahraini|Bangladeshi|Barbadian|Basotho|Belarusian|Belgian|Belizean|Beninese|Bhutanese|Bolivian|Bosnian and Herzegovinian|Botswanan|Brazilian|British|Bruneian|Bulgarian|Burkinabe|Burmese|Burundian|Cambodian|Cameroonian|Canadian|Cape Verdean|Central African|Chadian|Chilean|Chinese|Colombian|Comoran|Congolese (DR Congo)|Congolese (Republic of the Congo)|Costa Rican|Croatian|Cuban|Cypriot|Czech|Danish|Djiboutian|Dominican (Dominica)|Dominican (Dominican Republic)|Dutch|East Timorese|Ecuadorian|Egyptian|Emirati|Equatorial Guinean|Eritrean|Estonian|Eswatini|Ethiopian|Fijian|Filipino|Finnish|French|Gabonese|Gambian|Georgian|German|Ghanaian|Greek|Grenadian|Guatemalan|Guinean|Guinea-Bissauan|Guyanese|Haitian|Honduran|Hungarian|Icelandic|Indian|Indonesian|Iranian|Iraqi|Irish|Israeli|Italian|Ivorian|Jamaican|Japanese|Jordanian|Kazakhstani|Kenyan|Kiribati|Kittitian and Nevisian|Kosovar|Kuwaiti|Kyrgyz|Lao|Latvian|Lebanese|Liberian|Libyan|Liechtensteiner|Lithuanian|Luxembourgish|Malagasy|Malawian|Malaysian|Maldivian|Malian|Maltese|Marshallese|Mauritanian|Mauritian|Mexican|Micronesian|Moldovan|Monégasque|Mongolian|Montenegrin|Moroccan|Mozambican|Namibian|Nauruan|Nepalese|New Zealander|Nicaraguan|Nigerian|Nigerien|North Korean|North Macedonian|Norwegian|Omani|Pakistani|Palauan|Palestinian|Panamanian|Papua New Guinean|Paraguayan|Peruvian|Polish|Portuguese|Qatari|Romanian|Russian|Rwandan|Saint Lucian|Saint Vincentian|Salvadoran|Samoan|San Marinese|São Toméan|Saudi|Senegalese|Serbian|Seychellois|Sierra Leonean|Singaporean|Slovak|Slovenian|Solomon Islander|Somali|South African|South Korean|South Sudanese|Spanish|Sri Lankan|Sudanese|Surinamese|Swedish|Swiss|Syrian|Taiwanese|Tajik|Tanzanian|Thai|Togolese|Tongan|Trinidadian and Tobagonian|Tunisian|Turkish|Turkmen|Tuvaluan|Ugandan|Ukrainian|Uruguayan|Uzbekistani|Vanuatuan|Vatican|Venezuelan|Vietnamese|Yemeni|Zambian|Zimbabwean'),
            'priorityNations' => ['Malaysian', 'Singaporean', 'Chinese', 'Indonesian', 'Thai'],
            'packageUrl' => $packageUrl,
            'submitUrl' => $submitUrl,
            'editing' => $editing,
            'existingDocuments' => $existingDocuments,
            'documentMaxBytes' => 5 * 1024 * 1024,
            'today' => today()->toDateString(),
            'oldInput' => $oldInput,
            'errors' => $errors,
            'savedTravellerDetails' => $savedTravellerDetails,
        ];
    @endphp
    <section class="wrap booking-page" data-booking-root>
        <script type="application/json" data-booking-config>@json($bookingConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        <form class="booking-form" method="post" enctype="multipart/form-data" action="{{ $submitUrl }}" data-booking-form>
            @csrf
            <div data-booking-app></div>
            <noscript><p class="notice">Enable JavaScript to complete your booking.</p></noscript>
        </form>
    </section>
    <script type="module" src="{{ asset('js/booking.js') }}?v={{ filemtime(public_path('js/booking.js')) }}"></script>
@endif
@endsection

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 42px 48px; }
        body { color: #0f2238; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; }
        h1 { margin: 0 0 8px; font-size: 22px; }
        h2 { margin: 20px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #0f2238; font-size: 14px; }
        h3 { margin: 10px 0 3px; font-size: 11px; }
        p { margin: 5px 0; }
        .brand { color: #1f4e5f; font-size: 12px; font-weight: bold; }
        .muted { color: #56626f; }
        .day { margin: 8px 0; padding-bottom: 7px; border-bottom: 1px solid #ddd; }
        ul { margin: 5px 0; padding-left: 18px; }
        li { margin: 3px 0; }
        .fine-print { margin-top: 18px; color: #56626f; font-size: 9px; }
    </style>
</head>
<body>
    <p class="brand">TravelWithYou</p>
    <h1>{{ $package->name }}</h1>
    <p>{{ $package->country }} | {{ $package->type }} | {{ $package->days }} days | {{ $package->departure_port }} to {{ $package->destination_port }}</p>
    <p>From {{ fmt_rm($package->price_from) }} per person | Rated {{ number_format($package->rating, 1) }}/5 ({{ $package->reviews }} reviews)</p>
    <p>{{ $package->long_description ?: $package->details ?: $package->description }}</p>

    <h2>Departure dates</h2>
    <ul>
        @foreach($package->fares ?: array_map(fn ($date) => ['date' => $date], $package->departures) as $departure)
            @php($date = $departure['date'])
            @php($fare = \App\Support\Pricing::fareFor($package, $date))
            <li>
                {{ fmt_date($date) }}
                @if($fare)
                    — {{ fmt_rm(\App\Support\Pricing::fareUnitPrice($package, $fare)) }} per person
                    @if($fare['bogo'] ?? false) (buy 1 free 1, {{ fmt_rm($fare['price']) }} per pair)@endif
                    @if(!empty($fare['tag'])) [{{ $fare['tag'] }}]@endif
                    @if(!empty($fare['note'])) — {{ $fare['note'] }}@endif
                @endif
            </li>
        @endforeach
    </ul>

    <h2>Highlights</h2>
    <ul>@foreach($package->highlights ?: ($package->source_data['highlights'] ?? $package->source_data['attractions'] ?? $package->source_data['highlight_badges'] ?? []) as $highlight)<li>{{ is_array($highlight) ? ($highlight['name'] ?? $highlight['title'] ?? implode(' · ', array_filter($highlight, 'is_scalar'))) : $highlight }}</li>@endforeach</ul>

    <h2>Daily trip plan</h2>
    @foreach($package->dailyPlan() as $index => $day)
        <div class="day">
            <h3>Day {{ $index + 1 }} — {{ $day['place'] }}</h3>
            @if($day['activities'] !== '')<p>{{ $day['activities'] }}</p>@endif
            @if($day['transport'] !== '')<p>Transport: {{ $day['transport'] }}</p>@endif
        </div>
    @endforeach

    <h2>Included</h2>
    <ul>@foreach($package->included() as $item)<li>{{ $item }}</li>@endforeach</ul>
    <h2>Not included</h2>
    <ul>@foreach($package->excluded() as $item)<li>{{ $item }}</li>@endforeach</ul>

    @if($ship)
        <h2>Room types aboard {{ $ship->name }}</h2>
        <ul>@foreach($ship->cabins as $cabin)<li>{{ $cabin->type }} ({{ $cabin->size }}), from {{ fmt_rm($cabin->price_from) }}: {{ $cabin->note }}</li>@endforeach</ul>
    @endif
    <p class="fine-print">Prices are per person and subject to availability. See travelwithyou.com for full terms.</p>
</body>
</html>

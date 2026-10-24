@extends('layouts.app')
@section('title', 'Destinations — Travel With You')
@section('content')
<x-page-head kicker="Destinations" :title="$destinationTitle" lead="Choose a country to see every tour package we run there." />
<section class="wrap section-block destination-listing">
    <nav class="region-links" role="tablist" aria-label="Destination regions">
        <a role="tab" aria-selected="{{ $selectedRegion ? 'false' : 'true' }}" class="{{ $selectedRegion ? '' : 'is-selected' }}" href="{{ route('destinations') }}">All</a>
        @foreach($regions as $region)
            <a role="tab" aria-selected="{{ $selectedRegion === $region ? 'true' : 'false' }}" class="{{ $selectedRegion === $region ? 'is-selected' : '' }}" href="{{ route('destinations.region', ['region' => $region]) }}">{{ $region }}</a>
        @endforeach
    </nav>
    @foreach($destinations->groupBy('region') as $region => $countries)
        <section class="destination-region">
            <h2>{{ $region }} <span>· {{ $countries->count() }} countries</span></h2>
            <div class="destination-grid destination-grid-large">
                @foreach($countries as $destination)
                    @php($packageCount = $destination->packages->count())
                    <a class="destination-card" href="{{ route('destinations.country', ['country' => $destination->slug]) }}">
                        <span class="destination-card-image"><img src="{{ asset_img($destination->image) }}" alt="{{ $destination->name }}" loading="lazy"></span>
                        <span class="destination-card-copy">
                            <span>
                                <strong>{{ $destination->name }}</strong>
                                <small>{{ $packageCount }} {{ \Illuminate\Support\Str::plural('package', $packageCount) }}</small>
                            </span>
                            @if($packageCount)
                                <span class="destination-card-price"><small>From</small><strong>{{ fmt_rm($destination->packages->min('price_from')) }}</strong></span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</section>
@endsection

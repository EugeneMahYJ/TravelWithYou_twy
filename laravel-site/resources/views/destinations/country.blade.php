@extends('layouts.app')
@section('title', $country.' — Destinations — Travel With You')
@section('content')
<section class="country-hero">
    <img src="{{ asset_img($destination->image) }}" alt="{{ $country }}">
    <div class="country-hero-shade"></div>
    <div class="wrap country-hero-copy">
        <p class="country-breadcrumb"><a href="{{ route('destinations') }}">Destinations</a> / <a href="{{ route('destinations.region', ['region' => $region]) }}">{{ $region }}</a></p>
        <h1>{{ $country }}</h1>
        <p>{{ $destination->blurb }}</p>
    </div>
</section>
<section class="wrap country-listing">
    <form class="country-filters" method="get" action="{{ route('destinations.country', ['country' => $destination->slug]) }}" data-auto-submit>
        <label>
            <span>Sort by</span>
            <select name="sort">
                <option value="rating" @selected(request('sort', 'rating') === 'rating')>Rating</option>
                <option value="price-asc" @selected(request('sort') === 'price-asc')>Price: low to high</option>
                <option value="price-desc" @selected(request('sort') === 'price-desc')>Price: high to low</option>
                <option value="duration" @selected(request('sort') === 'duration')>Duration</option>
            </select>
        </label>
        <label>
            <span>Departure month</span>
            <select name="month">
                <option value="">Any</option>
                @foreach($months as $value => $label)
                    <option value="{{ $value }}" @selected(request('month') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Duration</span>
            <select name="duration">
                <option value="">Any</option>
                <option value="short" @selected(request('duration') === 'short')>Up to 9 days</option>
                <option value="long" @selected(request('duration') === 'long')>10+ days</option>
            </select>
        </label>
        <label>
            <span>Max price</span>
            <select name="max_price">
                <option value="">Any</option>
                <option value="2500" @selected(request('max_price') === '2500')>RM 2,500</option>
                <option value="3500" @selected(request('max_price') === '3500')>RM 3,500</option>
                <option value="5000" @selected(request('max_price') === '5000')>RM 5,000</option>
            </select>
        </label>
    </form>
    <p class="country-result-count">{{ $packages->count() }} tour {{ \Illuminate\Support\Str::plural('package', $packages->count()) }} in {{ $country }}</p>
    @if($packages->isEmpty())
        <p class="country-empty">No packages match those filters.</p>
    @else
        <div class="package-grid country-package-grid">
            @foreach($packages as $package)
                <x-package-card :package="$package" :show-country="false" />
            @endforeach
        </div>
    @endif
</section>
@endsection

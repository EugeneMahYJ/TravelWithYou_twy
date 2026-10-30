@extends('layouts.app')
@section('title', 'Search results — Travel With You')
@section('content')
@php
    $queryText = trim(request('q', ''));
    $resultCount = $packages->total();
    $resultLead = $resultCount.' '.\Illuminate\Support\Str::plural('trip', $resultCount).' match your filters.';
@endphp
<x-page-head kicker="Search results" :title="$queryText !== '' ? 'Results for “'.$queryText.'”' : 'Find your trip'" :lead="$resultLead" />
<section class="wrap search-results-section">
    <x-search-bar :options="$searchOptions" :preserve-scroll-on-clear="true" />
    <details class="more-filters">
        <summary>More filters</summary>
        <form method="get" action="{{ route('search') }}" class="more-filter-form">
            @foreach(['q', 'month', 'ship', 'dep_port', 'dest_port', 'price', 'duration'] as $preserved)
                @if(request()->filled($preserved))
                    <input type="hidden" name="{{ $preserved }}" value="{{ request($preserved) }}">
                @endif
            @endforeach
            <label>
                <span>Region</span>
                <select name="region">
                    <option value="">Any region</option>
                    @foreach($regions as $region)
                        <option value="{{ $region }}" @selected(request('region') === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Country</span>
                <select name="country">
                    <option value="">Any country</option>
                    @foreach($countries as $country)
                        <option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Travel style</span>
                <select name="type">
                    <option value="">Any style</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Sort</span>
                <select name="sort">
                    <option value="price-asc" @selected(request('sort', 'price-asc') === 'price-asc')>Price low→high</option>
                    <option value="price-desc" @selected(request('sort') === 'price-desc')>Price high→low</option>
                    <option value="rating" @selected(request('sort') === 'rating')>Rating</option>
                    <option value="duration" @selected(request('sort') === 'duration')>Duration</option>
                </select>
            </label>
            <button class="button button-primary" type="submit">Apply filters</button>
        </form>
    </details>
    @if($packages->isEmpty())
        <div class="search-empty">
            <p>No trips match those filters.</p>
            <a class="button button-ghost" href="{{ route('search') }}" data-preserve-scroll-on-clear>Clear filters</a>
        </div>
    @else
        <div class="package-grid search-results-grid">
            @foreach($packages as $package)
                <x-package-card :package="$package" />
            @endforeach
        </div>
        <div class="pagination">{{ $packages->links() }}</div>
    @endif
</section>
@endsection

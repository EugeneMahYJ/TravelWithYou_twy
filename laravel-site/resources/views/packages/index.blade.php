@extends('layouts.app')
@section('title', 'Search results — Travel With You')
@section('robots', request()->query() === [] ? 'index,follow' : 'noindex,follow')
@section('canonical', route('search'))
@section('content')
@php
    $queryText = trim(request('q', ''));
    $resultCount = $packages->total();
    $resultLead = $resultCount.' '.\Illuminate\Support\Str::plural('trip', $resultCount).' match your filters.';
    $filterLabels = [
        'q' => 'Destination',
        'month' => 'Departure month',
        'ship' => 'Ship',
        'dep_port' => 'Departure port',
        'dest_port' => 'Destination port',
        'price' => 'Price range',
        'region' => 'Region',
        'country' => 'Country',
        'type' => 'Travel style',
        'duration' => 'Duration',
    ];
    $filterValueLabels = [
        'price' => [
            'lt2' => 'Under RM 2,000',
            '2-3.5' => 'RM 2,000 – RM 3,500',
            '3.5-5' => 'RM 3,500 – RM 5,000',
            'gt5' => 'RM 5,000+',
        ],
        'duration' => [
            '1-7' => '1–7 days',
            '8-10' => '8–10 days',
            '11-14' => '11–14 days',
            '15' => '15+ days',
        ],
    ];
    $displayFilters = collect($filterLabels)
        ->filter(fn ($label, $key) => filled($activeFilters[$key] ?? null))
        ->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'value' => $key === 'month'
                ? $searchOptions['months']->get($activeFilters[$key], $activeFilters[$key])
                : ($filterValueLabels[$key][$activeFilters[$key]] ?? $activeFilters[$key]),
        ]);
    $suggestedFilters = $displayFilters->except('q');
@endphp
<x-page-head kicker="Search results" :title="$queryText !== '' ? 'Results for “'.$queryText.'”' : 'Find your trip'" :lead="$resultLead" />
<section class="wrap search-results-section">
    <x-search-bar :options="$searchOptions" :preserve-scroll-on-clear="true" :show-more-filters="true" :compact-on-mobile="true">
        <details class="more-filters">
            <summary>More filters</summary>
            <div class="more-filter-form">
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
            </div>
        </details>
    </x-search-bar>
    @if($packages->isEmpty())
        <div class="search-empty">
            <div class="search-empty-main">
                <h2>No trips match your search</h2>
                <p>Try relaxing one or two filters. Your destination search can stay in place while you adjust the rest.</p>
                @if($displayFilters->isNotEmpty())
                    <p class="search-empty-label">Your current search</p>
                    <ul class="search-empty-chip-list" aria-label="Current search filters">
                        @foreach($displayFilters as $filter)
                            <li>
                                <a class="search-empty-chip" href="{{ route('search', request()->except([$filter['key'], 'page'])) }}" aria-label="Remove {{ $filter['label'] }} filter" data-preserve-scroll-on-clear>
                                    <span>{{ $filter['key'] === 'q' ? $filter['value'] : $filter['label'].': '.$filter['value'] }}</span>
                                    <span aria-hidden="true">×</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <aside class="search-empty-suggestions" aria-label="Ways to broaden your search">
                <h3>Broaden your search</h3>
                @if($suggestedFilters->isNotEmpty())
                    <p>Remove a filter to see updated results.@if(request()->filled('q')) Your destination search will stay in place.@endif</p>
                    <ul>
                        @foreach($suggestedFilters as $filter)
                            <li>
                                <span>{{ $filter['label'] }}: {{ $filter['value'] }}</span>
                                <a href="{{ route('search', request()->except([$filter['key'], 'page'])) }}" aria-label="Remove {{ $filter['label'] }} filter" data-preserve-scroll-on-clear>Remove filter</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>No filters remain to remove. Try a different destination, or clear your search to browse all trips.</p>
                @endif
                <a class="button button-ghost" href="{{ route('search') }}" data-preserve-scroll-on-clear>Clear all filters</a>
            </aside>
        </div>
    @else
        <div class="package-grid search-results-grid">
            @foreach($packages as $package)
                <x-package-card :package="$package" :show-next-departure="true" />
            @endforeach
        </div>
        <div class="pagination">{{ $packages->links() }}</div>
    @endif
</section>
@endsection

@props(['options', 'action' => null, 'cta' => 'Search trips', 'clearUrl' => null, 'clearInPlace' => false])

<form class="search-panel hybrid-search-panel" action="{{ $action ?? route('search') }}" method="get" role="search">
    <label>
        <span>Where to?</span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Country or destination">
    </label>
    <label>
        <span>Departure Month</span>
        <select name="month">
            <option value="">Any month</option>
            @foreach($options['months'] as $value => $label)
                <option value="{{ $value }}" @selected(request('month') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span>Ship</span>
        <div class="filter-select" data-filter-select>
            <select name="ship">
                <option value="">Any ship</option>
                @foreach($options['ships'] as $ship)
                    <option value="{{ $ship }}" @selected(request('ship') === $ship)>{{ $ship }}</option>
                @endforeach
            </select>
            <button class="filter-select-trigger" type="button" data-filter-select-trigger aria-label="Ship" aria-haspopup="listbox" aria-expanded="false" hidden>
                <span data-filter-select-value>{{ request('ship') ?: 'Any ship' }}</span>
                <svg aria-hidden="true" viewBox="0 0 20 20"><path d="m4 7 6 6 6-6"></path></svg>
            </button>
            <div class="filter-select-menu" data-filter-select-menu hidden>
                <ul role="listbox" aria-label="Ship options" data-filter-select-options></ul>
            </div>
        </div>
    </label>
    <label>
        <span>Departure Port/City</span>
        <div class="filter-select" data-filter-select>
            <select name="dep_port">
                <option value="">Any port/city</option>
                @foreach($options['ports'] as $port)
                    <option value="{{ $port }}" @selected(request('dep_port') === $port)>{{ $port }}</option>
                @endforeach
            </select>
            <button class="filter-select-trigger" type="button" data-filter-select-trigger aria-label="Departure Port/City" aria-haspopup="listbox" aria-expanded="false" hidden>
                <span data-filter-select-value>{{ request('dep_port') ?: 'Any port/city' }}</span>
                <svg aria-hidden="true" viewBox="0 0 20 20"><path d="m4 7 6 6 6-6"></path></svg>
            </button>
            <div class="filter-select-menu" data-filter-select-menu hidden>
                <ul role="listbox" aria-label="Departure Port/City options" data-filter-select-options></ul>
            </div>
        </div>
    </label>
    <label>
        <span>Price Range</span>
        <select name="price">
            <option value="">Any price</option>
            <option value="lt2" @selected(request('price') === 'lt2')>Under RM 2,000</option>
            <option value="2-3.5" @selected(request('price') === '2-3.5')>RM 2,000 – RM 3,500</option>
            <option value="3.5-5" @selected(request('price') === '3.5-5')>RM 3,500 – RM 5,000</option>
            <option value="gt5" @selected(request('price') === 'gt5')>RM 5,000+</option>
        </select>
    </label>
    @foreach(['dest_port', 'region', 'country', 'type', 'duration', 'sort', 'tab'] as $preserved)
        @if(request()->filled($preserved))
            <input type="hidden" name="{{ $preserved }}" value="{{ request($preserved) }}">
        @endif
    @endforeach
    <div class="search-panel-actions">
        <a class="text-link" href="{{ $clearUrl ?? route('search') }}" @if($clearInPlace) data-clear-filters-in-place @endif>Clear filters</a>
        <button class="button button-primary" type="submit"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>{{ $cta }}</button>
    </div>
</form>

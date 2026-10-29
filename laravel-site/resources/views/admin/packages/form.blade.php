@extends('admin.layouts.admin')
@section('title', $package->exists ? 'Edit package' : 'New package')
@section('content')
@php
    $days = old('trip_plan', $package->dailyPlan());
    if (!$days) $days = [['place' => '', 'activities' => '', 'transport' => '']];
    $highlights = old('highlights', $package->highlights ?: ['']);
    $departures = old('departures', $package->departures ?: ['']);
    $included = old('included', $package->included());
    if (!is_array($included) || $included === []) $included = [''];
    $excluded = old('excluded', $package->excluded());
    if (!is_array($excluded) || $excluded === []) $excluded = [''];
    $pricing = $package->pricing ?? [];
    $guestTypes = old('guest_types', $pricing['guest_types'] ?? \App\Support\Pricing::GUEST_KINDS);
    $departurePrices = old('departure_prices');
    if ($departurePrices === null) {
        $departurePrices = array_map(function ($date) use ($pricing, $package, $guestTypes) {
            $saved = $pricing['departure_prices'][$date] ?? [];
            $fare = \App\Support\Pricing::fareFor($package, $date);
            $base = $fare ? \App\Support\Pricing::fareUnitPrice($package, $fare) : (float) $package->price_from;
            foreach ($guestTypes as $guestType) {
                $saved[$guestType['id']] ??= (int) round($base * (float) ($guestType['factor'] ?? 1));
            }
            return $saved;
        }, $departures);
    }
    $departurePaxTiers = old('departure_pax_tiers');
    if ($departurePaxTiers === null) {
        $departurePaxTiers = array_map(fn ($date) => $pricing['departure_pax_tiers'][$date] ?? [], $departures);
    }
    $addons = old('addons', $pricing['addons'] ?? \App\Support\Pricing::ADDONS);
    $selectedShipName = old('ship', $package->ship);
    $selectedShip = $ships->firstWhere('name', $selectedShipName);
    $cabinPrices = old('cabin_prices');
    if ($cabinPrices === null) {
        $cabinPrices = collect($selectedShip?->cabins ?? [])->map(fn ($cabin) => [
            'type' => $cabin->type,
            'price' => $pricing['cabin_prices'][$cabin->type] ?? ($cabin->price_from > 1 ? $cabin->price_from : ''),
        ])->all();
    }
@endphp
<div class="admin-heading"><div><p class="eyebrow accent">Package editor</p><h1>{{ $package->exists ? 'Edit package' : 'New package' }}</h1><p>Build the package shown to travellers.</p></div><a class="text-link" href="{{ route('admin.packages') }}">← Packages</a></div>
<form class="admin-editor-form" method="post" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}">
    @csrf @if($package->exists) @method('put') @endif
    <input type="hidden" name="builder_form" value="1">
    <section class="admin-editor-section"><h2>Cover image</h2>
        <label><span>Image URL or asset name</span><div class="admin-asset-field"><input id="package-cover-image" name="image" value="{{ old('image', $package->image) }}" placeholder="Image URL or public asset filename"><button class="button button-ghost" type="button" data-asset-picker-trigger data-asset-picker-kind="image" data-asset-picker-target="#package-cover-image">Browse library</button></div>@error('image')<small class="field-error">{{ $message }}</small>@enderror</label>
    </section>
    <section class="admin-editor-section"><h2>Package details</h2>
        <div class="form-grid">
            <label><span>Package name</span><input name="name" value="{{ old('name', $package->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>URL slug</span><input name="slug" value="{{ old('slug', $package->slug) }}" placeholder="Generated from name if blank">@error('slug')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Country</span><select name="country"><option value="">Choose a country</option>@foreach($countries as $country)<option value="{{ $country->slug }}" @selected(old('country', $package->countryRecord?->slug ?: \Illuminate\Support\Str::slug($package->country)) === $country->slug)>{{ $country->name }}</option>@endforeach</select>@error('country')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Trip type</span><select name="type" data-package-type><option value="">Choose a type</option>@foreach(['Flight + hotel', 'Cruise', 'Rail', 'Wildlife', 'Adventure', 'Culture'] as $type)<option value="{{ $type }}" @selected(old('type', $package->type) === $type)>{{ $type }}</option>@endforeach</select>@error('type')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Ship (cruises)</span><select name="ship" data-package-ship><option value="">Choose a ship</option>@foreach($ships as $ship)<option value="{{ $ship->name }}" data-cabins="{{ $ship->cabins->map(fn ($cabin) => ['type' => $cabin->type, 'size' => $cabin->size, 'note' => $cabin->note, 'legacyPrice' => $cabin->price_from])->values()->toJson() }}" @selected($selectedShipName === $ship->name)>{{ $ship->name }}</option>@endforeach</select>@error('ship')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Duration (days)</span><input type="number" min="1" max="365" name="days" value="{{ old('days', $package->days) }}">@error('days')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Nights</span><input type="number" min="0" max="365" name="nights" value="{{ old('nights', $package->nights) }}"></label>
            <label><span>Departure port or city</span><input name="departure_port" value="{{ old('departure_port', $package->departure_port) }}">@error('departure_port')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Destination port or city</span><input name="destination_port" value="{{ old('destination_port', $package->destination_port) }}">@error('destination_port')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="form-wide"><span>Short summary</span><textarea name="description" rows="2">{{ old('description', $package->description) }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="form-wide"><span>Long description</span><textarea name="long_description" rows="5">{{ old('long_description', $package->long_description ?: $package->details) }}</textarea>@error('long_description')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Highlights</h2><button class="text-button admin-add-row" type="button" data-repeat-add="highlights">+ Add highlight</button></div>
        <div class="admin-repeat-list" data-repeat-rows="highlights">@foreach($highlights as $highlight)<div class="admin-repeat-row"><input name="highlights[]" value="{{ $highlight }}" placeholder="A standout moment"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove highlight">Remove</button></div>@endforeach</div>
        <template data-repeat-template="highlights"><div class="admin-repeat-row"><input name="highlights[]" value="" placeholder="A standout moment"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove highlight">Remove</button></div></template>
        @error('highlights')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Guest types &amp; prices</h2><button class="text-button admin-add-row" type="button" data-add-guest-type>+ Add guest type</button></div>
        <p class="muted">Set guest categories and roles. Adult or Child roles determine cruise room allocation. Non-cruise prices are set separately for each departure date.</p>
        <div class="admin-repeat-list" data-guest-types>
            @foreach($guestTypes as $index => $guestType)
                <article class="admin-day-row" data-guest-type-row>
                    <div class="admin-day-row-heading"><strong>Guest type {{ $index + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove type</button></div>
                    <input type="hidden" data-guest-id name="guest_types[{{ $index }}][id]" value="{{ $guestType['id'] }}">
                    <div class="form-grid"><label><span>Guest type name</span><input name="guest_types[{{ $index }}][label]" value="{{ $guestType['label'] }}" required></label>
                        <label><span>Age / description</span><input name="guest_types[{{ $index }}][age]" value="{{ $guestType['age'] ?? '' }}"></label>
                        <label><span>Room role</span><select name="guest_types[{{ $index }}][role]"><option value="adult" @selected(($guestType['role'] ?? 'adult') === 'adult')>Adult</option><option value="child" @selected(($guestType['role'] ?? 'adult') === 'child')>Child</option></select></label>
                    </div>
                </article>
            @endforeach
        </div>
        <template data-guest-type-template><article class="admin-day-row" data-guest-type-row><div class="admin-day-row-heading"><strong>Guest type</strong><button type="button" class="text-button danger admin-remove-row">Remove type</button></div><input type="hidden" data-guest-id name="guest_types[__INDEX__][id]" value=""><div class="form-grid"><label><span>Guest type name</span><input name="guest_types[__INDEX__][label]" required></label><label><span>Age / description</span><input name="guest_types[__INDEX__][age]"></label><label><span>Room role</span><select name="guest_types[__INDEX__][role]"><option value="adult">Adult</option><option value="child">Child</option></select></label></div></article></template>
        @error('guest_types')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section" data-cruise-only>
        <div class="admin-panel-heading"><h2>Cruise cabin prices (RM per person)</h2></div>
        <p class="muted">Cabin details come from the ship. Set the price for this package; ship records no longer manage cabin prices.</p>
        <div class="admin-repeat-list" data-package-cabins>
            @foreach($cabinPrices as $index => $cabin)
                @php($shipCabin = $selectedShip?->cabins->firstWhere('type', $cabin['type']))
                <article class="admin-day-row" data-package-cabin><input type="hidden" name="cabin_prices[{{ $index }}][type]" value="{{ $cabin['type'] }}">
                    <div class="form-grid"><div><strong>{{ $cabin['type'] }}</strong><small class="muted">{{ $shipCabin?->size }}</small><p>{{ $shipCabin?->note }}</p></div>
                        <label><span>Package price (RM per person)</span><input type="number" min="1" name="cabin_prices[{{ $index }}][price]" value="{{ $cabin['price'] }}" required></label></div>
                </article>
            @endforeach
        </div>
        @error('cabin_prices')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Departure dates &amp; prices</h2><button class="text-button admin-add-row" type="button" data-add-departure>+ Add date</button></div>
        <p class="muted">Set an exact price for every guest type on every departure date. Flight + hotel packages can also use passenger-number price bands. Cruise packages use cabin prices instead.</p>
        <div class="admin-repeat-list" data-departure-prices>
            @foreach($departures as $index => $departure)
                <article class="admin-day-row" data-departure-row>
                    <div class="admin-day-row-heading"><strong>Departure {{ $index + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove date</button></div>
                    <label><span>Departure date</span><input type="date" name="departures[{{ $index }}]" value="{{ $departure }}" required></label>
                    <div class="form-grid" data-departure-price-fields>
                        @foreach($guestTypes as $guestType)
                            <label data-price-type="{{ $guestType['id'] }}"><span>{{ $guestType['label'] }} price (RM)</span><input type="number" min="1" name="departure_prices[{{ $index }}][{{ $guestType['id'] }}]" value="{{ $departurePrices[$index][$guestType['id']] ?? '' }}" @if(old('status', $package->admin_status) !== 'Draft' && old('type', $package->type) !== 'Cruise') required @endif></label>
                        @endforeach
                    </div>
                    <div class="admin-pax-tier-editor" data-flight-pax-pricing @if(old('type', $package->type) !== 'Flight + hotel') hidden @endif>
                        <div class="admin-panel-heading"><strong>Passenger price bands</strong><button class="text-button admin-add-row" type="button" data-add-pax-tier>Add band</button></div>
                        <p class="muted">Optional. A band overrides the regular departure price for those passenger numbers. Uncovered numbers use the regular price.</p>
                        <div class="admin-repeat-list" data-pax-tier-rows>
                            @foreach($departurePaxTiers[$index] ?? [] as $tierIndex => $tier)
                                <article class="admin-day-row" data-pax-tier-row>
                                    <div class="admin-day-row-heading"><strong>Band {{ $tierIndex + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove band</button></div>
                                    <div class="form-grid">
                                        <label><span>First pax number</span><input type="number" min="1" max="12" name="departure_pax_tiers[{{ $index }}][{{ $tierIndex }}][from]" value="{{ $tier['from'] ?? '' }}" @if(old('status', $package->admin_status) !== 'Draft') required @endif>@error('departure_pax_tiers.'.$index.'.'.$tierIndex.'.from')<small class="field-error">{{ $message }}</small>@enderror</label>
                                        <label><span>Last pax number</span><input type="number" min="1" max="12" name="departure_pax_tiers[{{ $index }}][{{ $tierIndex }}][to]" value="{{ $tier['to'] ?? '' }}" @if(old('status', $package->admin_status) !== 'Draft') required @endif>@error('departure_pax_tiers.'.$index.'.'.$tierIndex.'.to')<small class="field-error">{{ $message }}</small>@enderror</label>
                                        @foreach($guestTypes as $guestType)
                                            <label data-pax-price-type="{{ $guestType['id'] }}"><span>{{ $guestType['label'] }} price (RM)</span><input type="number" min="1" name="departure_pax_tiers[{{ $index }}][{{ $tierIndex }}][prices][{{ $guestType['id'] }}]" value="{{ $tier['prices'][$guestType['id']] ?? '' }}" @if(old('status', $package->admin_status) !== 'Draft') required @endif>@error('departure_pax_tiers.'.$index.'.'.$tierIndex.'.prices.'.$guestType['id'])<small class="field-error">{{ $message }}</small>@enderror</label>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @error('departure_pax_tiers.'.$index)<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </article>
            @endforeach
        </div>
        <template data-pax-tier-template><article class="admin-day-row" data-pax-tier-row><div class="admin-day-row-heading"><strong>Band</strong><button type="button" class="text-button danger admin-remove-row">Remove band</button></div><div class="form-grid"><label><span>First pax number</span><input type="number" min="1" max="12" name="departure_pax_tiers[__DEPARTURE__][__TIER__][from]" required></label><label><span>Last pax number</span><input type="number" min="1" max="12" name="departure_pax_tiers[__DEPARTURE__][__TIER__][to]" required></label></div></article></template>
        <template data-departure-template><article class="admin-day-row" data-departure-row><div class="admin-day-row-heading"><strong>Departure</strong><button type="button" class="text-button danger admin-remove-row">Remove date</button></div><label><span>Departure date</span><input type="date" name="departures[__INDEX__]" required></label><div class="form-grid" data-departure-price-fields></div><div class="admin-pax-tier-editor" data-flight-pax-pricing hidden><div class="admin-panel-heading"><strong>Passenger price bands</strong><button class="text-button admin-add-row" type="button" data-add-pax-tier>Add band</button></div><p class="muted">Optional. A band overrides the regular departure price for those passenger numbers. Uncovered numbers use the regular price.</p><div class="admin-repeat-list" data-pax-tier-rows></div></div></article></template>
        @error('departures')<small class="field-error">{{ $message }}</small>@enderror
        @error('departures.*')<small class="field-error">{{ $message }}</small>@enderror
        @error('departure_prices.*.*')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Daily trip plan</h2><button class="text-button admin-add-row" type="button" data-repeat-add="trip_plan">+ Add day</button></div>
        <p class="muted">Every day needs a place and activities. The number of days must match the package duration.</p>
        <div class="admin-repeat-list admin-day-list" data-repeat-rows="trip_plan">@foreach($days as $index => $day)
            <article class="admin-day-row"><div class="admin-day-row-heading"><strong>Day {{ $index + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove day</button></div>
                <div class="form-grid"><label><span>Place</span><input name="trip_plan[{{ $index }}][place]" value="{{ data_get($day, 'place', data_get($day, 0, '')) }}"></label>
                <label><span>Transport</span><input name="trip_plan[{{ $index }}][transport]" value="{{ data_get($day, 'transport', data_get($day, 2, '')) }}"></label>
                <label class="form-wide"><span>Activities</span><textarea name="trip_plan[{{ $index }}][activities]" rows="2">{{ data_get($day, 'activities', data_get($day, 1, '')) }}</textarea></label></div>
            </article>
        @endforeach</div>
        <template data-repeat-template="trip_plan"><article class="admin-day-row"><div class="admin-day-row-heading"><strong>Day</strong><button type="button" class="text-button danger admin-remove-row">Remove day</button></div><div class="form-grid"><label><span>Place</span><input name="trip_plan[__INDEX__][place]"></label><label><span>Transport</span><input name="trip_plan[__INDEX__][transport]"></label><label class="form-wide"><span>Activities</span><textarea name="trip_plan[__INDEX__][activities]" rows="2"></textarea></label></div></article></template>
        @error('trip_plan')<small class="field-error">{{ $message }}</small>@enderror
        @error('trip_plan.*.place')<small class="field-error">{{ $message }}</small>@enderror
        @error('trip_plan.*.activities')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <h2>Included / Not included</h2>
        <div class="admin-inclusion-preview">
            <div>
                <div class="admin-panel-heading"><strong>Included</strong><button class="text-button admin-add-row" type="button" data-repeat-add="included">+ Add item</button></div>
                <input type="hidden" name="included_present" value="1">
                <div class="admin-repeat-list" data-repeat-rows="included">
                    @foreach($included as $index => $item)
                        <div class="admin-repeat-row"><input name="included[]" value="{{ $item }}" placeholder="Included service or item"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove included item">Remove</button>@error('included.'.$index)<small class="field-error">{{ $message }}</small>@enderror</div>
                    @endforeach
                </div>
                <template data-repeat-template="included"><div class="admin-repeat-row"><input name="included[]" value="" placeholder="Included service or item"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove included item">Remove</button></div></template>
                @error('included')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div>
                <div class="admin-panel-heading"><strong>Not included</strong><button class="text-button admin-add-row" type="button" data-repeat-add="excluded">+ Add item</button></div>
                <input type="hidden" name="excluded_present" value="1">
                <div class="admin-repeat-list" data-repeat-rows="excluded">
                    @foreach($excluded as $index => $item)
                        <div class="admin-repeat-row"><input name="excluded[]" value="{{ $item }}" placeholder="Excluded service or item"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove excluded item">Remove</button>@error('excluded.'.$index)<small class="field-error">{{ $message }}</small>@enderror</div>
                    @endforeach
                </div>
                <template data-repeat-template="excluded"><div class="admin-repeat-row"><input name="excluded[]" value="" placeholder="Excluded service or item"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove excluded item">Remove</button></div></template>
                @error('excluded')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </section>
    <section class="admin-editor-section"><h2>Status &amp; pricing</h2><div class="form-grid">
        <label><span>Status</span><select name="status">@foreach(['Published', 'Expiring', 'Draft'] as $status)<option @selected(old('status', $package->admin_status) === $status)>{{ $status }}</option>@endforeach</select></label>
        <label><span>Price from (RM)</span><input type="number" min="1" name="price_from" value="{{ old('price_from', $package->price_from) }}">@error('price_from')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Price to (RM)</span><input type="number" min="1" name="price_to" value="{{ old('price_to', $package->price_to) }}">@error('price_to')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Brand</span><input name="brand" value="{{ old('brand', $package->brand) }}"></label><label><span>Tour operator</span><input name="operator" value="{{ old('operator', $package->operator) }}"></label>
    </div></section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Optional add-ons</h2><button class="text-button admin-add-row" type="button" data-add-package-addon>+ Add add-on</button></div>
        <p class="muted">Each add-on is charged per guest when selected at booking.</p>
        <div class="admin-repeat-list" data-package-addons>
            @foreach($addons as $index => $addon)
                <article class="admin-day-row" data-package-addon><div class="admin-day-row-heading"><strong>Add-on {{ $index + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove add-on</button></div>
                    <input type="hidden" data-addon-id name="addons[{{ $index }}][id]" value="{{ $addon['id'] }}">
                    <div class="form-grid"><label><span>Name</span><input data-addon-label name="addons[{{ $index }}][label]" value="{{ $addon['label'] }}" required></label><label><span>Price per guest (RM)</span><input type="number" min="0" name="addons[{{ $index }}][price]" value="{{ $addon['price'] }}" required></label></div>
                </article>
            @endforeach
        </div>
        <template data-package-addon-template><article class="admin-day-row" data-package-addon><div class="admin-day-row-heading"><strong>Add-on</strong><button type="button" class="text-button danger admin-remove-row">Remove add-on</button></div><input type="hidden" data-addon-id name="addons[__INDEX__][id]" value=""><div class="form-grid"><label><span>Name</span><input data-addon-label name="addons[__INDEX__][label]" required></label><label><span>Price per guest (RM)</span><input type="number" min="0" name="addons[__INDEX__][price]" required></label></div></article></template>
    </section>
    <label class="form-wide"><span>Destinations (one per line)</span><textarea name="destinations_text" rows="3">{{ old('destinations_text', implode("\n", $package->destinations ?? [])) }}</textarea></label>
    <div class="form-actions"><button class="button button-primary" type="submit">Save package</button><a class="text-link" href="{{ route('admin.packages') }}">Cancel</a></div>
</form>
@include('admin.partials.asset-picker', ['mediaAssets' => $mediaAssets])
@if($package->exists)<form class="delete-form" method="post" action="{{ route('admin.packages.delete', $package) }}">@csrf @method('delete')<button class="text-button danger" type="submit" onclick="return confirm('Delete this package?')">Delete package</button></form>@endif
@endsection

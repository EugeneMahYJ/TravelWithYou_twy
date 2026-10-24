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
            <label><span>Trip type</span><select name="type"><option value="">Choose a type</option>@foreach(['Flight + hotel', 'Cruise', 'Rail', 'Wildlife', 'Adventure', 'Culture'] as $type)<option value="{{ $type }}" @selected(old('type', $package->type) === $type)>{{ $type }}</option>@endforeach</select>@error('type')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Ship (cruises)</span><select name="ship"><option value="">Choose a ship</option>@foreach($ships as $ship)<option value="{{ $ship->name }}" @selected(old('ship', $package->ship) === $ship->name)>{{ $ship->name }}</option>@endforeach</select>@error('ship')<small class="field-error">{{ $message }}</small>@enderror</label>
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
        <div class="admin-panel-heading"><h2>Departure dates</h2><button class="text-button admin-add-row" type="button" data-repeat-add="departures">+ Add date</button></div>
        <div class="admin-repeat-list" data-repeat-rows="departures">@foreach($departures as $departure)<div class="admin-repeat-row"><input type="date" name="departures[]" value="{{ $departure }}"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove departure">Remove</button></div>@endforeach</div>
        <template data-repeat-template="departures"><div class="admin-repeat-row"><input type="date" name="departures[]" value=""><button type="button" class="text-button danger admin-remove-row" aria-label="Remove departure">Remove</button></div></template>
        @error('departures')<small class="field-error">{{ $message }}</small>@enderror
        @error('departures.*')<small class="field-error">{{ $message }}</small>@enderror
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
    <label class="form-wide"><span>Destinations (one per line)</span><textarea name="destinations_text" rows="3">{{ old('destinations_text', implode("\n", $package->destinations ?? [])) }}</textarea></label>
    <details class="admin-advanced"><summary>Advanced · brochure data</summary><label><span>Brochure data (JSON)</span><textarea name="source_data_json" rows="12" spellcheck="false">{{ old('source_data_json', $package->source_data ? json_encode($package->source_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea><small class="muted">Keep the existing source structure intact. Valid JSON is required.</small>@error('source_data_json')<small class="field-error">{{ $message }}</small>@enderror</label></details>
    <div class="form-actions"><button class="button button-primary" type="submit">Save package</button><a class="text-link" href="{{ route('admin.packages') }}">Cancel</a></div>
</form>
@include('admin.partials.asset-picker', ['mediaAssets' => $mediaAssets])
@if($package->exists)<form class="delete-form" method="post" action="{{ route('admin.packages.delete', $package) }}">@csrf @method('delete')<button class="text-button danger" type="submit" onclick="return confirm('Delete this package?')">Delete package</button></form>@endif
@endsection

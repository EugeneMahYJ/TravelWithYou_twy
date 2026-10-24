@extends('admin.layouts.admin')
@section('title', $ship->exists ? 'Edit ship' : 'New ship')
@php
    $gallery = old('gallery');
    if ($gallery === null) {
        $gallery = $ship->gallery ?? [];
    }
    if (! is_array($gallery) || $gallery === []) {
        $gallery = [''];
    }

    $amenities = old('amenities');
    if ($amenities === null) {
        $amenities = $ship->amenities ?? [];
    }
    if (! is_array($amenities) || $amenities === []) {
        $amenities = [''];
    }

    $decks = old('decks');
    if ($decks === null) {
        $decks = $ship->decks ?? [];
    }
    if (! is_array($decks) || $decks === []) {
        $decks = [['n' => '', 'label' => '', 'items' => '']];
    }

    $cabins = old('cabins');
    if ($cabins === null) {
        $cabins = $ship->exists
            ? $ship->cabins->map(fn ($cabin) => [
                'type' => $cabin->type,
                'size' => $cabin->size,
                'price_from' => $cabin->price_from,
                'note' => $cabin->note,
            ])->all()
            : [];
    }
    if (! is_array($cabins) || $cabins === []) {
        $cabins = [['type' => '', 'size' => '', 'price_from' => '', 'note' => '']];
    }
@endphp
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Fleet</p><h1>{{ $ship->exists ? 'Edit ship' : 'New ship' }}</h1><p>Changes are published to the client website as soon as you save.</p></div>
    <a class="text-link" href="{{ route('admin.ships') }}">← Ships</a>
</div>
<form class="admin-editor-form" method="post" action="{{ $ship->exists ? route('admin.ships.update', $ship) : route('admin.ships.store') }}">
    @csrf @if($ship->exists) @method('put') @endif
    <section class="admin-editor-section"><h2>Ship details</h2>
        <div class="form-grid">
            <label><span>Ship name</span><input name="name" value="{{ old('name', $ship->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>URL slug</span><input name="slug" value="{{ old('slug', $ship->slug) }}" placeholder="Generated from name if blank">@error('slug')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Class</span><input name="class_name" value="{{ old('class_name', $ship->class_name) }}" list="ship-class-options" required>@error('class_name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Year built</span><input type="number" name="year" min="1900" max="{{ now()->year + 5 }}" value="{{ old('year', $ship->year) }}" required>@error('year')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Capacity (guests)</span><input type="number" name="capacity" min="1" max="10000" value="{{ old('capacity', $ship->capacity) }}" required>@error('capacity')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Crew</span><input type="number" name="crew" min="1" max="5000" value="{{ old('crew', $ship->crew) }}" required>@error('crew')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Rating (0–5)</span><input type="number" name="rating" min="0" max="5" step="0.1" value="{{ old('rating', $ship->rating) }}" required>@error('rating')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label><span>Cover image</span><div class="admin-asset-field"><input id="ship-cover-image" name="image" value="{{ old('image', $ship->image) }}" placeholder="Asset name or image URL" required><button class="button button-ghost" type="button" data-asset-picker-trigger data-asset-picker-kind="image" data-asset-picker-target="#ship-cover-image">Browse library</button></div>@error('image')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        <datalist id="ship-class-options">
            @foreach($shipClasses as $shipClass)
                <option value="{{ $shipClass }}"></option>
            @endforeach
        </datalist>
    </section>
    <section class="admin-editor-section"><h2>Overview</h2>
        <label><span>Description shown on the ship page</span><textarea name="overview" rows="5" required>{{ old('overview', $ship->overview) }}</textarea>@error('overview')<small class="field-error">{{ $message }}</small>@enderror</label>
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Gallery</h2><button class="text-button admin-add-row" type="button" data-repeat-add="gallery">+ Add media</button></div>
        <div class="admin-repeat-list" data-repeat-rows="gallery">
            @foreach($gallery as $image)
                <div class="admin-repeat-row" data-repeat-item><input name="gallery[]" value="{{ $image }}" placeholder="Asset name, image URL, or video:filename.mp4"><button class="button button-ghost" type="button" data-asset-picker-trigger data-asset-picker-kind="all">Browse</button><button type="button" class="text-button danger admin-remove-row" aria-label="Remove media">Remove</button></div>
            @endforeach
        </div>
        <template data-repeat-template="gallery"><div class="admin-repeat-row" data-repeat-item><input name="gallery[]" value="" placeholder="Asset name, image URL, or video:filename.mp4"><button class="button button-ghost" type="button" data-asset-picker-trigger data-asset-picker-kind="all">Browse</button><button type="button" class="text-button danger admin-remove-row" aria-label="Remove media">Remove</button></div></template>
        <p class="muted">For a video in public/assets, enter video:path. For example, video:Ship_example.mp4 for assets/video, or video:uploads/clip.mp4 for an uploaded video. Videos appear as gallery tiles.</p>
        @error('gallery')<small class="field-error">{{ $message }}</small>@enderror
        @error('gallery.*')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Amenities</h2><button class="text-button admin-add-row" type="button" data-repeat-add="amenities">+ Add amenity</button></div>
        <div class="admin-repeat-list" data-repeat-rows="amenities">
            @foreach($amenities as $amenity)
                <div class="admin-repeat-row" data-repeat-item><input name="amenities[]" value="{{ $amenity }}" placeholder="Pool, spa, theatre…"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove amenity">Remove</button></div>
            @endforeach
        </div>
        <template data-repeat-template="amenities"><div class="admin-repeat-row" data-repeat-item><input name="amenities[]" value="" placeholder="Pool, spa, theatre…"><button type="button" class="text-button danger admin-remove-row" aria-label="Remove amenity">Remove</button></div></template>
        @error('amenities')<small class="field-error">{{ $message }}</small>@enderror
        @error('amenities.*')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Decks</h2><button class="text-button admin-add-row" type="button" data-repeat-add="decks">+ Add deck</button></div>
        <div class="admin-repeat-list" data-repeat-rows="decks" data-repeat-indexed="decks">
            @foreach($decks as $deck)
                @php($deckIndex = $loop->index)
                <article class="admin-day-row" data-repeat-item>
                    <div class="admin-day-row-heading"><strong>Deck {{ $deckIndex + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove deck</button></div>
                    <div class="form-grid">
                        <label><span>Deck number</span><input name="decks[{{ $deckIndex }}][n]" value="{{ data_get($deck, 'n', '') }}" placeholder="Deck 14">@error('decks.'.$deckIndex.'.n')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Deck name</span><input name="decks[{{ $deckIndex }}][label]" value="{{ data_get($deck, 'label', '') }}" placeholder="Sun Deck">@error('decks.'.$deckIndex.'.label')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label class="form-wide"><span>On this deck</span><input name="decks[{{ $deckIndex }}][items]" value="{{ data_get($deck, 'items', '') }}" placeholder="Infinity pool, sky bar">@error('decks.'.$deckIndex.'.items')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                </article>
            @endforeach
        </div>
        <template data-repeat-template="decks"><article class="admin-day-row" data-repeat-item><div class="admin-day-row-heading"><strong>Deck</strong><button type="button" class="text-button danger admin-remove-row">Remove deck</button></div><div class="form-grid"><label><span>Deck number</span><input name="decks[__INDEX__][n]" placeholder="Deck 14"></label><label><span>Deck name</span><input name="decks[__INDEX__][label]" placeholder="Sun Deck"></label><label class="form-wide"><span>On this deck</span><input name="decks[__INDEX__][items]" placeholder="Infinity pool, sky bar"></label></div></article></template>
        @error('decks')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="admin-editor-section">
        <div class="admin-panel-heading"><h2>Cabins</h2><button class="text-button admin-add-row" type="button" data-repeat-add="cabins">+ Add cabin</button></div>
        <p class="muted">Cabin types must be unique per ship and are offered when a traveller books a cruise.</p>
        <div class="admin-repeat-list" data-repeat-rows="cabins" data-repeat-indexed="cabins">
            @foreach($cabins as $cabin)
                @php($cabinIndex = $loop->index)
                <article class="admin-day-row" data-repeat-item>
                    <div class="admin-day-row-heading"><strong>Cabin {{ $cabinIndex + 1 }}</strong><button type="button" class="text-button danger admin-remove-row">Remove cabin</button></div>
                    <div class="form-grid">
                        <label><span>Cabin type</span><input name="cabins[{{ $cabinIndex }}][type]" value="{{ data_get($cabin, 'type', '') }}" placeholder="Balcony">@error('cabins.'.$cabinIndex.'.type')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Size</span><input name="cabins[{{ $cabinIndex }}][size]" value="{{ data_get($cabin, 'size', '') }}" placeholder="24 m²">@error('cabins.'.$cabinIndex.'.size')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Price from (RM)</span><input type="number" name="cabins[{{ $cabinIndex }}][price_from]" min="1" value="{{ data_get($cabin, 'price_from', '') }}">@error('cabins.'.$cabinIndex.'.price_from')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Note</span><input name="cabins[{{ $cabinIndex }}][note]" value="{{ data_get($cabin, 'note', '') }}" placeholder="Private veranda, lounge area">@error('cabins.'.$cabinIndex.'.note')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                </article>
            @endforeach
        </div>
        <template data-repeat-template="cabins"><article class="admin-day-row" data-repeat-item><div class="admin-day-row-heading"><strong>Cabin</strong><button type="button" class="text-button danger admin-remove-row">Remove cabin</button></div><div class="form-grid"><label><span>Cabin type</span><input name="cabins[__INDEX__][type]" placeholder="Balcony"></label><label><span>Size</span><input name="cabins[__INDEX__][size]" placeholder="24 m²"></label><label><span>Price from (RM)</span><input type="number" name="cabins[__INDEX__][price_from]" min="1"></label><label><span>Note</span><input name="cabins[__INDEX__][note]" placeholder="Private veranda, lounge area"></label></div></article></template>
        @error('cabins')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <div class="form-actions"><button class="button button-primary" type="submit">Save ship</button><a class="text-link" href="{{ route('admin.ships') }}">Cancel</a></div>
</form>
@if($ship->exists)
    <form class="delete-form" method="post" action="{{ route('admin.ships.delete', $ship) }}">@csrf @method('delete')<button class="text-button danger" type="submit" onclick="return confirm('Delete this ship? Cruise packages assigned to it will keep their itinerary but lose the ship link.')">Delete ship</button></form>
@endif
@include('admin.partials.asset-picker', ['mediaAssets' => $mediaAssets])
@endsection

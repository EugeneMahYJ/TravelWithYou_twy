@extends('layouts.app')
@section('title', 'Cart — TravelWithYou')
@section('content')
@include('partials.page-head', ['kicker' => 'Cart', 'heading' => 'Your trips.'])
@php
    $taxLabel = rtrim(rtrim(number_format($taxRate * 100, 2, '.', ''), '0'), '.');
    $promoLabel = $totals['promo'] instanceof \App\Models\PromoCode && $totals['promo']->discount_type === 'fixed'
        ? fmt_rm($totals['promo']->discount_amount)
        : rtrim(rtrim(number_format((float) ($totals['promo']->discount_percent ?? 0), 2, '.', ''), '0'), '.').'%';
@endphp
<section class="wrap section-block">
    @if($items->isEmpty())
        <div class="cart-empty">
            <p class="font-display">Your cart is empty.</p>
            <a class="button button-primary" href="{{ route('destinations') }}">Continue Shopping</a>
        </div>
    @else
        <div class="cart-layout">
            <div class="cart-list">
                @if($unavailableItems->isNotEmpty())
                    <div class="notice notice-warn" role="status">{{ $unavailableItems->count() }} {{ \Illuminate\Support\Str::plural('package', $unavailableItems->count()) }} need{{ $unavailableItems->count() === 1 ? 's' : '' }} a new date before you can check out.</div>
                @endif
                @foreach($items as $item)
                    @php
                        $package = $item['package'];
                        $detail = $item['display_detail'] ?? [];
                        $wizard = ! empty($item['detail']);
                        $ship = $package->ship ? \App\Models\Ship::with('cabins')->where('name', $package->ship)->first() : null;
                        $addons = collect(\App\Support\Pricing::ADDONS)->keyBy('id');
                    @endphp
                    <article class="cart-row cart-row-full">
                        <img src="{{ asset_img($package->image) }}" alt="{{ $package->country }} landscape">
                        <div class="cart-row-copy">
                            <p class="eyebrow">{{ $package->country }} · {{ $package->days }} days</p>
                            <h2><a href="{{ route('packages.show', ['package' => $package->slug]) }}">{{ $package->name }}</a></h2>
                            @if($wizard)
                                <div class="cart-detail">
                                    <p><strong>Departure:</strong> {{ fmt_date($item['departure_date']) }}</p>
                                    <p><strong>{{ $item['guests'] }} guests</strong>
                                        @if(($detail['kind'] ?? '') === 'cruise')
                                            · {{ collect($detail['rooms'] ?? [])->map(fn ($room, $index) => 'Room '.($index + 1).': '.($room['type'] ?? 'type pending'))->join(', ') }}
                                        @elseif(($detail['kind'] ?? '') === 'fh' && !empty($detail['rooms']))
                                            · {{ count($detail['rooms']) }} {{ \Illuminate\Support\Str::plural('room', count($detail['rooms'])) }}
                                        @endif
                                    </p>
                                    <p><strong>Lead booker:</strong> {{ data_get($detail, 'guests.0.name', 'Traveller') }}
                                        @if(!empty($item['addons'])) · <strong>Add-ons:</strong> {{ collect($item['addons'])->map(fn ($id) => $addons->get($id)['label'] ?? $id)->join(', ') }} @endif
                                    </p>
                                    <a href="{{ route('booking', ['id' => $package->slug, 'date' => $item['departure_date'], 'cart_item' => $item['uid']]) }}">Edit booking</a>
                                </div>
                                @if(!\App\Support\Pricing::itemAvailable($item))
                                    <p class="cart-expired">The {{ fmt_date($item['departure_date']) }} departure is no longer available. Choose a new date to keep this package.</p>
                                @endif
                            @else
                                <form class="cart-edit-form" method="post" action="{{ route('cart.update', ['uid' => $item['uid']]) }}">
                                    @csrf
                                    @method('patch')
                                    <label>Departure
                                        <select name="departure_date" required>
                                            @if(!\App\Support\Pricing::itemAvailable($item))
                                                <option value="{{ $item['departure_date'] }}" selected disabled>{{ fmt_date($item['departure_date']) }} — no longer available</option>
                                            @endif
                                            @foreach(\App\Support\Pricing::availableDepartureDates($package) as $date)
                                                <option value="{{ $date }}" @selected($date === $item['departure_date'])>{{ fmt_date($date) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @if($ship)
                                        <label>Cabin
                                            <select name="cabin">
                                                <option value="">Any cabin</option>
                                                @foreach($ship->cabins as $cabin)
                                                    <option value="{{ $cabin->type }}" @selected(($item['cabin'] ?? '') === $cabin->type)>{{ $cabin->type }} — {{ fmt_rm(\App\Support\Pricing::unitPrice($package, $item['guests'], $item['departure_date'], $cabin->type)) }} pp</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                    <label>Pax
                                        <input type="number" name="pax" min="1" max="12" value="{{ $item['guests'] }}">
                                    </label>
                                    <div class="cart-addon-options">
                                        @foreach($addons as $addon)
                                            <label><input type="checkbox" name="addons[]" value="{{ $addon['id'] }}" @checked(in_array($addon['id'], $item['addons'] ?? [], true))> {{ $addon['label'] }} +{{ fmt_rm($addon['price']) }} pp</label>
                                        @endforeach
                                    </div>
                                    <button class="button button-ghost" type="submit">Update trip</button>
                                </form>
                                <p class="cart-unit">Price per pax · {{ fmt_rm(\App\Support\Pricing::unitPrice($package, $item['guests'], $item['departure_date'], $item['cabin'] ?? null)) }}</p>
                            @endif
                            <strong class="cart-row-total">{{ fmt_rm(\App\Support\Pricing::itemTotal($item)) }}</strong>
                        </div>
                        <form method="post" action="{{ route('cart.remove', ['uid' => $item['uid']]) }}">
                            @csrf
                            @method('delete')
                            <button class="remove-button" type="submit" aria-label="Remove {{ $package->name }}">Remove</button>
                        </form>
                    </article>
                @endforeach
            </div>
            <aside class="booking-card cart-summary">
                <p class="eyebrow">Order summary</p>
                <form class="promo-form" method="post" action="{{ route('cart.promo') }}">@csrf
                    <label for="promo-code">Promo code</label>
                    <div><input id="promo-code" name="code" value="{{ old('code', session('promo_code')) }}" autocomplete="off" placeholder="WELCOME10"><button class="button button-ghost" type="submit">Apply</button></div>
                    @if($promoCode)<small class="field-success">{{ $promoCode }} applied.</small>@endif
                </form>
                <div class="summary-line"><span>Subtotal</span><strong>{{ fmt_rm($totals['subtotal']) }}</strong></div>
                <div class="summary-line"><span>Taxes / fees ({{ $taxLabel }}%)</span><strong>{{ fmt_rm($totals['taxes']) }}</strong></div>
                <div class="summary-line"><span>Discount{{ $promoCode ? ' ('.$promoCode.', '.$promoLabel.')' : '' }}</span><strong>{{ $promoCode ? '− '.fmt_rm($totals['discount']) : 'RM 0' }}</strong></div>
                <div class="summary-line summary-total"><span>Total</span><strong>{{ fmt_rm($totals['total']) }}</strong></div>
                @if($unavailableItems->isNotEmpty())
                    <p class="notice notice-warn">Update the unavailable dates to check out.</p>
                @endif
                <a class="button button-primary button-full {{ $unavailableItems->isNotEmpty() ? 'is-disabled' : '' }}" href="{{ $unavailableItems->isNotEmpty() ? '#' : route('checkout') }}" @if($unavailableItems->isNotEmpty()) aria-disabled="true" @endif>Proceed to Checkout</a>
                <a class="button button-ghost button-full" href="{{ route('destinations') }}">Continue Shopping</a>
            </aside>
        </div>
    @endif
</section>
@endsection

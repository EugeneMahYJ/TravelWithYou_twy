@extends('layouts.app')
@section('title', 'Checkout — TravelWithYou')
@section('content')
@include('partials.page-head', ['kicker' => 'Checkout', 'heading' => 'Almost there.'])
@php
    $taxLabel = rtrim(rtrim(number_format($taxRate * 100, 2, '.', ''), '0'), '.');
    $promoLabel = $totals['promo'] instanceof \App\Models\PromoCode && $totals['promo']->discount_type === 'fixed'
        ? fmt_rm($totals['promo']->discount_amount)
        : rtrim(rtrim(number_format((float) ($totals['promo']->discount_percent ?? 0), 2, '.', ''), '0'), '.').'%';
@endphp
<section class="wrap checkout-layout checkout-port">
    @if($unavailableItems->isNotEmpty())
        <div class="checkout-expired notice notice-warn">
            Your cart has expired items:
            {{ $unavailableItems->map(fn ($item) => $item['package']->name)->join(', ') }}.
            <a href="{{ route('cart') }}">Update your cart</a> to continue.
        </div>
    @endif
    @guest
        <div class="guest-checkout form-card">
            <label><input type="radio" name="checkout-account" checked> Guest checkout</label>
            <label><input type="radio" name="checkout-account" onchange="window.location='{{ route('login') }}'"> I have an account, <a href="{{ route('login') }}">log in</a></label>
        </div>
    @endguest
    <form class="checkout-form" method="post" action="{{ route('checkout.place') }}" data-checkout-form novalidate>
        @csrf
        <div class="checkout-sections">
            <section class="checkout-card form-card">
                <h2><span>01</span>Contact details</h2>
                <div class="checkout-fields">
                    <label>Full name<input name="name" value="{{ old('name', $contactDefaults['name']) }}" autocomplete="name">@error('name')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Email<input type="email" name="email" value="{{ old('email', $contactDefaults['email']) }}" autocomplete="email">@error('email')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Phone number<input type="tel" name="phone" value="{{ old('phone', $contactDefaults['phone']) }}" autocomplete="tel">@error('phone')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label class="full-span">Billing address<textarea name="contact_address" rows="2" autocomplete="street-address">{{ old('contact_address', $contactDefaults['address']) }}</textarea>@error('contact_address')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                </div>
            </section>
            <section class="checkout-card form-card">
                <h2><span>02</span>Traveller details</h2>
                <div class="checkout-fields">
                    <label>Full name as per passport<input name="traveller_name" value="{{ old('traveller_name', $travellerDefaults['name'] ?? '') }}">@error('traveller_name')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Date of birth<input type="date" name="traveller_dob" value="{{ old('traveller_dob', $travellerDefaults['dob'] ?? '') }}">@error('traveller_dob')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Nationality<input name="nationality" list="checkout-nationalities" value="{{ old('nationality', $travellerDefaults['nationality'] ?? '') }}"><datalist id="checkout-nationalities">@foreach($nations as $nation)<option value="{{ $nation }}">@endforeach</datalist>@error('nationality')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Passport number<input name="passport_number" value="{{ old('passport_number', $travellerDefaults['passport_number'] ?? '') }}" autocomplete="off">@error('passport_number')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label>Passport expiry<input type="date" name="passport_expiry" value="{{ old('passport_expiry', $travellerDefaults['passport_expiry'] ?? '') }}">@error('passport_expiry')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label class="full-span">Dietary, medical or accessibility requests<textarea name="requests" rows="3">{{ old('requests', $travellerDefaults['requests'] ?? '') }}</textarea>@error('requests')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                </div>
            </section>
            <section class="checkout-card form-card">
                <h2><span>03</span>Payment</h2>
                <div class="payment-tabs" role="tablist" aria-label="Payment method">
                    @foreach(['card' => 'Card', 'paypal' => 'PayPal', 'bank' => 'Bank transfer'] as $method => $label)
                        <button type="button" role="tab" aria-selected="{{ old('payment_method', 'card') === $method ? 'true' : 'false' }}" data-payment-tab="{{ $method }}">{{ $label }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="payment_method" value="{{ old('payment_method', 'card') }}" data-payment-method>
                <div class="payment-panel" data-payment-panel="card">
                    @if($paymentGateway && $paymentGateway->hostedFieldsView() !== null)
                        {!! $paymentGateway->hostedFieldsView() !!}
                        <input type="hidden" name="payment_token" value="">
                    @elseif($paymentGateway)
                        <div class="checkout-fields">
                            <label class="full-span">Card number<input name="card_number" inputmode="numeric" maxlength="16" autocomplete="cc-number" placeholder="4242 4242 4242 4242">@error('card_number')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                            <label>Name on card<input name="card_name" autocomplete="cc-name">@error('card_name')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                            <label>Expiry (MM/YY)<input name="card_expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="08/29">@error('card_expiry')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                            <label>CVC<input name="card_cvc" inputmode="numeric" maxlength="4" autocomplete="cc-csc">@error('card_cvc')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                        </div>
                    @else
                        <p class="checkout-help">Card payments will be recorded as Pending payment until a payment gateway is configured.</p>
                    @endif
                </div>
                <div class="payment-panel" data-payment-panel="paypal" hidden>
                    <p>You will be redirected to PayPal to authorise payment after you confirm.</p>
                </div>
                <div class="payment-panel" data-payment-panel="bank" hidden>
                    <p>Bank details and your booking reference are emailed after you confirm. Your booking is held for 48 hours.</p>
                </div>
                <label class="checkout-checkbox"><input type="checkbox" name="billing_same" value="1" @checked(old('billing_same', '1') === '1') data-billing-same> Billing address same as contact</label>
                <label class="billing-address-field" data-billing-address hidden>Billing address<input name="billing_address" value="{{ old('billing_address') }}">@error('billing_address')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                <p class="checkout-help">Payments are encrypted and processed securely.</p>
            </section>
            <section class="checkout-card form-card">
                <h2><span>04</span>Terms &amp; consent</h2>
                @foreach([
                    'terms' => ['I accept the Terms & Conditions', route('terms'), 'Please accept the Terms & Conditions'],
                    'privacy' => ['I accept the Privacy Policy', route('privacy'), 'Please accept the Privacy Policy'],
                    'cancellation' => ['I acknowledge the Cancellation & Refund Policy', route('terms').'#terms-cancellation', 'Please acknowledge the cancellation policy'],
                ] as $field => [$label, $url, $message])
                    <label class="checkout-checkbox"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field))> {{ $label }} <a href="{{ $url }}" target="_blank" rel="noopener">(read)</a></label>
                    @error($field)<small class="field-error" role="alert">{{ $message }}</small>@enderror
                @endforeach
            </section>
        </div>
        <aside class="checkout-summary booking-card">
            <h2>Booking summary</h2>
            <ul class="checkout-items">
                @foreach($items as $item)
                    @php
                        $package = $item['package'];
                        $wizardCabins = collect(data_get($item, 'detail.rooms', []))->pluck('type')->filter()->unique();
                        $cabinForPrice = $item['cabin'] ?? $wizardCabins->first();
                        $cabinLabel = $item['cabin'] ?? $wizardCabins->join(', ');
                        $roomCount = count(data_get($item, 'detail.rooms', []));
                    @endphp
                    <li>
                        <strong>{{ $package->name }}</strong>
                        <span class="{{ \App\Support\Pricing::itemAvailable($item) ? '' : 'checkout-expired-text' }}">{{ \App\Support\Pricing::itemAvailable($item) ? fmt_date($item['departure_date']) : 'Date unavailable' }} · {{ $item['guests'] }} pax × {{ fmt_rm(\App\Support\Pricing::unitPrice($package, $item['guests'], $item['departure_date'], $cabinForPrice)) }}{{ $cabinLabel ? ' · '.$cabinLabel : '' }}{{ data_get($item, 'detail.kind') === 'fh' && $roomCount ? ' · '.$roomCount.' '.\Illuminate\Support\Str::plural('room', $roomCount) : '' }}</span>
                        @if(!empty($item['addons']))<small>Add-ons: {{ collect($item['addons'])->map(fn ($id) => collect(\App\Support\Pricing::ADDONS)->firstWhere('id', $id)['label'] ?? $id)->join(', ') }}</small>@endif
                        <b>{{ fmt_rm(\App\Support\Pricing::itemTotal($item)) }}</b>
                    </li>
                @endforeach
            </ul>
            <div class="promo-form">
                <label for="checkout-promo">Promo code</label>
                <div><input id="checkout-promo" name="code" value="{{ old('code', session('promo_code')) }}" placeholder="WELCOME10"><button type="submit" name="apply_promo" value="1">Apply</button></div>
                @if($promoCode)<small class="field-success">{{ $promoCode }} applied.</small>@endif
            </div>
            <div class="summary-line"><span>Subtotal</span><strong>{{ fmt_rm($totals['subtotal']) }}</strong></div>
            <div class="summary-line"><span>Taxes / fees ({{ $taxLabel }}%)</span><strong>{{ fmt_rm($totals['taxes']) }}</strong></div>
            <div class="summary-line"><span>Discount{{ $promoCode ? ' ('.$promoCode.', '.$promoLabel.')' : '' }}</span><strong>{{ $promoCode ? '− '.fmt_rm($totals['discount']) : 'RM 0' }}</strong></div>
            <div class="summary-line summary-total"><span>Total</span><strong>{{ fmt_rm($totals['total']) }}</strong></div>
            @if($unavailableItems->isNotEmpty())
                <p class="notice notice-warn">Update your cart</p>
            @endif
            <button class="button button-primary button-full" type="submit" data-confirm-button @disabled($unavailableItems->isNotEmpty())>Confirm Booking</button>
            <p class="checkout-help">You can apply a promo code and review everything once more at payment.</p>
        </aside>
    </form>
</section>
<script src="{{ asset('js/checkout.js') }}" defer></script>
@endsection

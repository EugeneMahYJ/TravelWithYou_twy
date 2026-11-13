@extends('layouts.app')
@section('title', 'Checkout — Travel With You')
@section('robots', 'noindex')
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
                <h2><span>02</span>Payment</h2>
                <p class="payment-intro">Choose a payment method. Methods that need a payment provider will become available once a provider is connected.</p>
                <div class="payment-method-list" role="radiogroup" aria-label="Payment method">
                    <button class="payment-method-option" type="button" role="radio" aria-checked="false" data-payment-option="online_banking" data-payment-label="Online Banking" aria-controls="payment-details-online_banking" tabindex="-1">
                        <span class="payment-method-radio" aria-hidden="true"></span>
                        <span class="payment-method-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="m3 9 9-5 9 5M4 10h16M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18"/></svg>
                        </span>
                        <span class="payment-method-copy"><strong>Online Banking</strong><small>FPX · select your bank</small></span>
                        <span class="payment-method-status">Coming soon</span>
                    </button>
                    <button class="payment-method-option" type="button" role="radio" aria-checked="false" data-payment-option="tng" data-payment-label="Touch ’n Go" aria-controls="payment-details-tng" tabindex="-1">
                        <span class="payment-method-radio" aria-hidden="true"></span>
                        <span class="payment-method-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M4 7.5h14a2 2 0 0 1 2 2v9H5a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2h13v4"/><path d="M15 12h6v4h-6a2 2 0 0 1 0-4Z"/><path d="M16.5 14h.01"/></svg>
                        </span>
                        <span class="payment-method-copy"><strong>Touch ’n Go eWallet</strong><small>Approve securely in the TNG app</small></span>
                        <span class="payment-method-status">Coming soon</span>
                    </button>
                    <button class="payment-method-option" type="button" role="radio" aria-checked="false" data-payment-option="duitnow" data-payment-label="DuitNow" aria-controls="payment-details-duitnow" tabindex="-1">
                        <span class="payment-method-radio" aria-hidden="true"></span>
                        <span class="payment-method-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="M14 14h2v2h-2zm4 0h2v2h-2zm-4 4h2v2h-2zm4 0h2v2h-2zm2-2h2v4h-2z"/></svg>
                        </span>
                        <span class="payment-method-copy"><strong>DuitNow</strong><small>Scan a one-time QR code</small></span>
                        <span class="payment-method-status">Coming soon</span>
                    </button>
                    <button class="payment-method-option" type="button" role="radio" aria-checked="true" data-payment-option="card" data-payment-label="Card" aria-controls="payment-details-card">
                        <span class="payment-method-radio" aria-hidden="true"></span>
                        <span class="payment-method-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 9h18M6 15h4"/></svg>
                        </span>
                        <span class="payment-method-copy"><strong>Visa / Mastercard</strong><small>Debit or credit card</small></span>
                        <span class="payment-method-status">
                            @if($paymentGateway instanceof \App\Payments\SimulatedPaymentGateway)
                                Demo
                            @elseif($paymentGateway)
                                Available
                            @else
                                Pending
                            @endif
                        </span>
                    </button>
                    <button class="payment-method-option" type="button" role="radio" aria-checked="false" data-payment-option="cash" data-payment-label="Cash" aria-controls="payment-details-cash" tabindex="-1">
                        <span class="payment-method-radio" aria-hidden="true"></span>
                        <span class="payment-method-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M5.5 9a2 2 0 0 0 2-2m9 10a2 2 0 0 1 2-2"/></svg>
                        </span>
                        <span class="payment-method-copy"><strong>Cash at our office</strong><small>Payment held for 48 hours</small></span>
                    </button>
                </div>
                <input type="hidden" name="payment_method" value="card" data-payment-method>
                <div class="payment-detail-panel" id="payment-details-online_banking" data-payment-panel="online_banking" hidden>
                    <h3>Pay through FPX online banking</h3>
                    <p>Choose a participating bank and authorize payment through its secure online banking service. Payment confirmation will be provided by the payment provider.</p>
                    <label class="payment-bank-select">Choose your bank
                        <select data-payment-bank aria-describedby="payment-bank-note">
                            <option value="">Choose your bank</option>
                            <option value="maybank">Maybank</option>
                            <option value="cimb">CIMB Bank</option>
                            <option value="public-bank">Public Bank</option>
                            <option value="rhb">RHB Bank</option>
                            <option value="hong-leong">Hong Leong Bank</option>
                            <option value="bank-islam">Bank Islam</option>
                            <option value="ambank">AmBank</option>
                            <option value="bank-rakyat">Bank Rakyat</option>
                            <option value="bsn">BSN</option>
                            <option value="uob">UOB Malaysia</option>
                            <option value="ocbc">OCBC Bank</option>
                        </select>
                    </label>
                    <small id="payment-bank-note">Example banks for layout only. The actual list depends on your payment provider.</small>
                </div>
                <div class="payment-detail-panel" id="payment-details-tng" data-payment-panel="tng" hidden>
                    <h3>Pay with Touch ’n Go eWallet</h3>
                    <p>Once connected, you’ll continue to the provider’s secure payment page and approve the request in your TNG eWallet app.</p>
                    <p class="payment-unavailable-note">This method is not available yet. No payment will be taken.</p>
                </div>
                <div class="payment-detail-panel" id="payment-details-duitnow" data-payment-panel="duitnow" hidden>
                    <h3>Pay with DuitNow QR</h3>
                    <p>A one-time QR code will be created for this order after a provider is connected. Scan it with a supported banking or eWallet app.</p>
                    <p class="payment-unavailable-note">This method is not available yet. No payment will be taken.</p>
                </div>
                <div class="payment-detail-panel" id="payment-details-card" data-payment-panel="card">
                    @if($paymentGateway && $paymentGateway->hostedFieldsView() !== null)
                        {!! $paymentGateway->hostedFieldsView() !!}
                        <input type="hidden" name="payment_token" value="">
                    @elseif($paymentGateway)
                        @if($paymentGateway instanceof \App\Payments\SimulatedPaymentGateway)
                            <p class="payment-unavailable-note">Demo checkout only — card payments are simulated and no real payment is processed.</p>
                        @endif
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
                <div class="payment-detail-panel" id="payment-details-cash" data-payment-panel="cash" hidden>
                    <h3>Pay cash at our office</h3>
                    <p>Your booking is held for 48 hours. It remains awaiting payment until staff confirm that cash has been received. Nothing is charged online.</p>
                </div>
                <p class="payment-selection-error" data-payment-selection-error role="alert" hidden>Please choose card or cash at our office, or return when your selected payment method is ready.</p>
                <label class="checkout-checkbox"><input type="checkbox" name="billing_same" value="1" @checked(old('billing_same', '1') === '1') data-billing-same> Billing address same as contact</label>
                <label class="billing-address-field" data-billing-address hidden>Billing address<input name="billing_address" value="{{ old('billing_address') }}">@error('billing_address')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
                <p class="checkout-help">Payments are encrypted and processed securely.</p>
            </section>
            <section class="checkout-card form-card">
                <h2><span>03</span>Terms &amp; consent</h2>
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
            <button class="button button-primary button-full" type="submit" data-confirm-button data-cash-label="Reserve Booking" data-card-label="{{ $paymentGateway instanceof \App\Payments\SimulatedPaymentGateway ? 'Confirm Booking (Demo)' : 'Confirm Booking' }}" data-cart-unavailable="{{ $unavailableItems->isNotEmpty() ? 'true' : 'false' }}" @disabled($unavailableItems->isNotEmpty())>
                {{ $paymentGateway instanceof \App\Payments\SimulatedPaymentGateway ? 'Confirm Booking (Demo)' : 'Confirm Booking' }}
            </button>
            <p class="checkout-help">You can apply a promo code and review everything once more at payment.</p>
        </aside>
    </form>
</section>
<script src="{{ asset('js/checkout.js') }}" defer></script>
@endsection

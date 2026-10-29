@extends('layouts.app')
@section('title', 'My account — Travel With You')
@section('content')
@php
    $tabs = [
        'profile' => 'Profile',
        'bookings' => 'Bookings',
        'wishlist' => 'Wishlist',
        'travellers' => 'Travellers',
        'payments' => 'Payment methods',
        'notifications' => 'Notifications',
        'support' => 'Support',
        'security' => 'Security',
    ];
@endphp
<section class="account-heading wrap">
    <p class="eyebrow accent">My account</p>
    <h1>Hello, {{ $user->name }}.</h1>
</section>
<section class="account-layout wrap">
    <nav class="account-navigation" aria-label="Account sections">
        <div class="account-tabs" role="tablist" aria-label="Account sections">
            @foreach($tabs as $key => $label)
                <a class="account-tab {{ $activeTab === $key ? 'is-active' : '' }}" href="{{ route('account', ['tab' => $key]) }}" role="tab" id="account-tab-{{ $key }}" aria-controls="account-panel-{{ $key }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}" data-account-tab="{{ $key }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="post" action="{{ route('logout') }}" class="account-logout-form">@csrf
            <button class="account-logout" type="submit">Log out</button>
        </form>
    </nav>
    <div class="account-content">
        <section class="account-panel" id="account-panel-profile" role="tabpanel" aria-labelledby="account-tab-profile" data-account-panel="profile" @if($activeTab !== 'profile') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Your details</p><h2>Profile</h2><p>Keep your account information up to date.</p></div>
            <form class="account-form account-profile-form" method="post" action="{{ route('account.profile.update') }}">@csrf @method('put')
                <x-field name="username" label="Username" hint="Your username is unique and is used to log in.">
                    <x-text-input name="username" :value="$user->username" autocomplete="username" required data-credential-username />
                </x-field>
                <x-field name="email" label="Email">
                    <x-text-input name="email" type="email" :value="$user->email" autocomplete="email" required />
                </x-field>
                <x-field name="phone" label="Phone">
                    <x-text-input name="phone" type="tel" :value="$user->phone" autocomplete="tel" />
                </x-field>
                <x-field name="password_display" label="Password" hint="Passwords are changed in the Security tab.">
                    <x-text-input name="password_display" value="••••••••" disabled aria-label="Password is hidden and can be changed in Security" />
                </x-field>
                <div class="account-form-actions"><button class="button button-primary" type="submit">Save changes</button></div>
            </form>
        </section>

        <section class="account-panel" id="account-panel-bookings" role="tabpanel" aria-labelledby="account-tab-bookings" data-account-panel="bookings" @if($activeTab !== 'bookings') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Your journeys</p><h2>Bookings</h2></div>
            <div class="account-section-heading"><h3>Upcoming trips ({{ $upcomingBookings->count() }})</h3></div>
            <div class="account-trip-list">
                @forelse($upcomingBookings as $booking)
                    @include('partials.account-booking', ['completed' => false])
                @empty
                    <p class="account-muted">No upcoming trips.</p>
                @endforelse
            </div>
            <div class="account-section-heading account-history-heading"><h3>Booking history</h3></div>
            <div class="account-trip-list">
                @forelse($completedBookings as $booking)
                    @include('partials.account-booking', ['completed' => true])
                @empty
                    @if($upcomingBookings->isEmpty())
                        <div class="empty-state"><h2>No journeys booked yet</h2><p>There is plenty of time to find a good one.</p><a class="button button-primary" href="{{ route('search') }}">Explore journeys</a></div>
                    @else
                        <p class="account-muted">Completed journeys will appear here.</p>
                    @endif
                @endforelse
            </div>
        </section>

        <section class="account-panel" id="account-panel-wishlist" role="tabpanel" aria-labelledby="account-tab-wishlist" data-account-panel="wishlist" @if($activeTab !== 'wishlist') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Saved for later</p><h2>Wishlist</h2></div>
            @if($favorites->isEmpty())
                <div class="empty-state"><h2>No saved packages yet.</h2><a class="button button-primary" href="{{ route('search') }}">Explore journeys</a></div>
            @else
                <div class="account-wishlist-grid">
                    @foreach($favorites as $package)
                        <a class="account-wishlist-card" href="{{ route('packages.show', ['package' => $package->slug]) }}">
                            <img src="{{ asset_img($package->image) }}" alt="{{ $package->country }}" loading="lazy">
                            <span>{{ $package->name }}</span>
                            <strong>From RM {{ number_format($package->price_from) }}</strong>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="account-panel" id="account-panel-travellers" role="tabpanel" aria-labelledby="account-tab-travellers" data-account-panel="travellers" @if($activeTab !== 'travellers') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Travel details</p><h2>Travellers</h2><p>Save traveller details for easier trip planning.</p></div>
            <form class="account-form account-fields-grid" method="post" action="{{ route('account.travellers.update') }}">@csrf @method('put')
                <x-field name="ic_number" label="IC number"><x-text-input name="ic_number" :value="data_get($travellerDetails, 'ic_number')" placeholder="900101-14-5678" /></x-field>
                <x-field name="name_as_per_ic" label="Name as per IC"><x-text-input name="name_as_per_ic" :value="data_get($travellerDetails, 'name_as_per_ic')" autocomplete="name" /></x-field>
                <x-field name="passport_number" label="Passport number"><x-text-input name="passport_number" :value="data_get($travellerDetails, 'passport_number')" placeholder="X1234567" /></x-field>
                <x-field name="passport_expiry" label="Passport expiry date"><x-text-input name="passport_expiry" type="date" :value="data_get($travellerDetails, 'passport_expiry')" /></x-field>
                <x-field name="nationality" label="Nationality"><x-text-input name="nationality" :value="data_get($travellerDetails, 'nationality')" autocomplete="country-name" /></x-field>
                <x-field name="date_of_birth" label="Date of birth"><x-text-input name="date_of_birth" type="date" :value="data_get($travellerDetails, 'date_of_birth')" /></x-field>
                <x-field name="billing_address" label="Billing address" class="account-field-wide"><textarea id="billing_address" name="billing_address" rows="3" class="account-input">{{ old('billing_address', data_get($travellerDetails, 'billing_address')) }}</textarea></x-field>
                <x-field name="dietary_needs" label="Dietary / accessibility needs" class="account-field-wide"><textarea id="dietary_needs" name="dietary_needs" rows="3" placeholder="e.g. vegetarian, step-free access" class="account-input">{{ old('dietary_needs', data_get($travellerDetails, 'dietary_needs')) }}</textarea></x-field>
                <div class="account-form-actions"><button class="button button-primary" type="submit">Save traveller</button></div>
            </form>
        </section>

        <section class="account-panel" id="account-panel-payments" role="tabpanel" aria-labelledby="account-tab-payments" data-account-panel="payments" @if($activeTab !== 'payments') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Your wallet</p><h2>Payment methods</h2></div>
            <div class="account-payment-card">
                <span class="account-payment-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20M6 15h4"></path></svg></span>
                <div><strong>Visa ending 4242</strong><span>Expires 08/29</span></div>
            </div>
            <form method="post" action="{{ route('account.action') }}">@csrf<input type="hidden" name="action" value="add-payment"><button class="button button-ghost" type="submit">Add payment method</button></form>
        </section>

        <section class="account-panel" id="account-panel-notifications" role="tabpanel" aria-labelledby="account-tab-notifications" data-account-panel="notifications" @if($activeTab !== 'notifications') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Stay in the loop</p><h2>Notifications</h2></div>
            <form class="account-form account-preferences" method="post" action="{{ route('account.notifications.update') }}">@csrf @method('put')
                <label class="account-preference"><span><strong>New deals and offers</strong><small>Occasional notes when a new journey goes on offer.</small></span><input type="checkbox" name="deals" value="1" @checked($notificationPreferences['deals'])></label>
                <label class="account-preference"><span><strong>Reminders for upcoming trips</strong><small>Helpful reminders before your departure.</small></span><input type="checkbox" name="trips" value="1" @checked($notificationPreferences['trips'])></label>
                <label class="account-preference"><span><strong>Monthly newsletter</strong><small>Travel stories and inspiration, once a month.</small></span><input type="checkbox" name="news" value="1" @checked($notificationPreferences['news'])></label>
                <div class="account-form-actions"><button class="button button-primary" type="submit">Save changes</button></div>
            </form>
        </section>

        <section class="account-panel" id="account-panel-support" role="tabpanel" aria-labelledby="account-tab-support" data-account-panel="support" @if($activeTab !== 'support') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Here to help</p><h2>Support</h2></div>
            <article class="account-support-ticket">
                <p class="eyebrow">12 Sep 2026 · Resolved</p>
                <h3>Change travel date for TWY-48215</h3>
                <p>Date moved to 23 Jan 2027 at no cost.</p>
            </article>
            <a class="button button-primary" href="{{ route('contact') }}">New support request</a>
        </section>

        <section class="account-panel" id="account-panel-security" role="tabpanel" aria-labelledby="account-tab-security" data-account-panel="security" @if($activeTab !== 'security') hidden @endif>
            <div class="account-panel-heading"><p class="eyebrow">Keep your account safe</p><h2>Security</h2></div>
            <form class="account-form account-security-form" method="post" action="{{ route('account.password.update') }}">@csrf @method('put')
                <h3>Change password</h3>
                <x-field name="current_password" label="Current password"><x-text-input name="current_password" type="password" autocomplete="current-password" required /></x-field>
                <x-field name="new_password" label="New password"><x-text-input name="new_password" type="password" autocomplete="new-password" required data-credential-password /></x-field>
                <x-field name="new_password_confirmation" label="Confirm new password"><x-text-input name="new_password_confirmation" type="password" autocomplete="new-password" required /></x-field>
                <div class="account-form-actions"><button class="button button-primary" type="submit">Update password</button></div>
            </form>
            <div class="account-security-setting">
                <div><strong>Two-factor authentication</strong><p>{{ $user->account_mfa_enabled ? 'Enabled via authenticator app' : 'Add an extra layer of security' }}</p></div>
                <form method="post" action="{{ route('account.two-factor.update') }}">@csrf @method('put')
                    <input type="hidden" name="enabled" value="0">
                    <label class="account-switch"><input type="checkbox" name="enabled" value="1" @checked($user->account_mfa_enabled) onchange="this.form.submit()"><span class="account-switch-track"></span><span class="visually-hidden">Toggle two-factor authentication</span></label>
                </form>
            </div>
            <form method="post" action="{{ route('account.action') }}">@csrf<input type="hidden" name="action" value="logout-devices"><button class="button button-ghost" type="submit">Log out from all devices</button></form>
        </section>
    </div>
</section>
@endsection

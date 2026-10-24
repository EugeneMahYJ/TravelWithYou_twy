@extends('admin.layouts.admin')
@section('title', 'Booking '.$booking->reference)
@section('content')
<div class="admin-heading"><div><p class="eyebrow accent">Booking detail</p><h1>{{ $booking->reference }}</h1><p>{{ $booking->package?->name }} · {{ $booking->departure_date->format('j M Y') }}</p></div><a class="text-link" href="{{ route('admin.bookings') }}">← All bookings</a></div>
<div class="admin-booking-detail-grid">
    <section class="admin-panel"><div class="admin-panel-heading"><h2>Booking summary</h2><span class="status-pill">{{ $booking->status }}</span></div><dl class="admin-detail-list">
        <div><dt>Customer</dt><dd>{{ $booking->user?->name ?? data_get($booking->guest_details, 'name', 'Guest') }}</dd></div>
        <div><dt>Email</dt><dd>{{ $booking->user?->email ?? data_get($booking->guest_details, 'email', '—') }}</dd></div>
        <div><dt>Guests</dt><dd>{{ $booking->guests }}</dd></div>
        <div><dt>Subtotal</dt><dd>{{ fmt_rm($booking->subtotal) }}</dd></div>
        <div><dt>Taxes / fees</dt><dd>{{ fmt_rm($booking->taxes) }}</dd></div>
        <div><dt>Total</dt><dd>{{ fmt_rm($booking->total) }}</dd></div>
        <div><dt>Payment method</dt><dd>{{ ucfirst($booking->payment_method ?? '—') }}</dd></div>
        <div><dt>Room types</dt><dd>{{ collect(data_get($booking->guest_details, 'booking_detail.room_types', []))->pluck('type')->filter()->implode(', ') ?: '—' }}</dd></div>
        <div><dt>Add-ons</dt><dd>{{ collect(data_get($booking->guest_details, 'addons', []))->pluck('name')->filter()->implode(', ') ?: '—' }}</dd></div>
    </dl>
    <form class="admin-status-update" method="post" action="{{ route('admin.status', ['resource' => 'bookings', 'id' => $booking->id]) }}">@csrf @method('patch')<label><span>Booking status</span><select name="status">@foreach(['Confirmed', 'Pending payment', 'Cancelled'] as $status)<option @selected($booking->status === $status)>{{ $status }}</option>@endforeach</select></label><button class="button button-ghost" type="submit">Update status</button></form>
    </section>
    <section class="admin-panel"><h2>Travellers</h2>
        @php($travellers = data_get($booking->guest_details, 'travellers', []))
        @php($passports = $booking->traveller_passports ?? [])
        @if($booking->passport_number)<div class="admin-traveller-card"><strong>Lead booker</strong><dl><div><dt>Passport</dt><dd>{{ str_repeat('*', max(0, strlen($booking->passport_number) - 3)).substr($booking->passport_number, -3) }}</dd></div></dl></div>@endif
        @forelse($travellers as $index => $traveller)<article class="admin-traveller-card"><strong>{{ $traveller['name'] ?? 'Guest '.($index + 1) }}</strong><dl><div><dt>Date of birth</dt><dd>{{ $traveller['dob'] ?? '—' }}</dd></div><div><dt>Nationality</dt><dd>{{ $traveller['nationality'] ?? '—' }}</dd></div><div><dt>Passport</dt><dd>@if(isset($passports[$index]) && $passports[$index] !== ''){{ str_repeat('*', max(0, strlen($passports[$index]) - 3)).substr($passports[$index], -3) }}@else—@endif</dd></div></dl></article>@empty
            @if(!$booking->passport_number)<p class="muted">No traveller details were provided.</p>@endif
        @endforelse
    </section>
</div>
@endsection

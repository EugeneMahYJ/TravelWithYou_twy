@extends('admin.layouts.admin')
@section('title', 'Booking '.$booking->reference)
@section('content')
<div class="admin-heading"><div><p class="eyebrow accent">Booking detail</p><h1>{{ $booking->reference }}</h1><p>{{ $booking->package?->name ?? 'Package unavailable' }} · {{ $booking->departure_date->format('j M Y') }}</p></div><a class="text-link" href="{{ route('admin.bookings') }}">← All bookings</a></div>
<div class="admin-booking-detail-grid">
    <div class="admin-booking-column">
        <section class="admin-panel">
            <div class="admin-panel-heading"><h2>Booking summary</h2><span class="status-pill">{{ $booking->status }}</span></div>
            <dl class="admin-detail-list">
                <div><dt>Booked by</dt><dd>{{ $bookedBy ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $bookerEmail ?: '—' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $bookerPhone ?: '—' }}</dd></div>
                <div><dt>Lead traveller</dt><dd>{{ $leadTraveller ?: '—' }}</dd></div>
                <div><dt>Guests</dt><dd>{{ $booking->guests }}</dd></div>
                <div><dt>Rooms</dt><dd>{{ $roomCount ?: '—' }}</dd></div>
                <div><dt>Room types</dt><dd>{{ $roomTypes->isNotEmpty() ? $roomTypes->implode(', ') : '—' }}</dd></div>
                <div><dt>Add-ons</dt><dd>{{ $addons->isNotEmpty() ? $addons->implode(', ') : 'None' }}</dd></div>
                <div><dt>Contact address</dt><dd>{{ $contactAddress ?: '—' }}</dd></div>
                <div><dt>Billing address</dt><dd>{{ $billingAddress ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="admin-panel">
            <h2>Payment</h2>
            <dl class="admin-detail-list">
                <div><dt>Subtotal</dt><dd>{{ fmt_rm($booking->subtotal) }}</dd></div>
                @if($booking->discount > 0)<div><dt>Discount</dt><dd>−{{ fmt_rm($booking->discount) }}</dd></div>@endif
                <div><dt>Taxes / fees</dt><dd>{{ fmt_rm($booking->taxes) }}</dd></div>
                <div class="admin-payment-total"><dt>Total</dt><dd>{{ fmt_rm($booking->total) }}</dd></div>
                <div><dt>Payment method</dt><dd>{{ $booking->payment_method ? ucfirst($booking->payment_method) : '—' }}</dd></div>
                <div><dt>Order reference</dt><dd>{{ $booking->order_reference ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="admin-panel">
            <h2>Status</h2>
            <form class="admin-status-update" method="post" action="{{ route('admin.status', ['resource' => 'bookings', 'id' => $booking->id]) }}">
                @csrf
                @method('patch')
                <label><span>Booking status</span><select name="status">@foreach(['Confirmed', 'Pending payment', 'Cancelled'] as $status)<option @selected($booking->status === $status)>{{ $status }}</option>@endforeach</select></label>
                <button class="button button-ghost" type="submit">Update status</button>
            </form>
            @if($booking->status === 'Cancelled')
                <dl class="admin-detail-list admin-cancellation-details">
                    <div><dt>Cancelled at</dt><dd>{{ $booking->cancelled_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                    <div><dt>Cancelled by</dt><dd>{{ $booking->cancelledBy?->name ?? '—' }}</dd></div>
                </dl>
            @endif
        </section>
    </div>

    <section class="admin-panel admin-travellers-panel">
        <div class="admin-panel-heading">
            <h2>Travellers</h2>
            <span class="admin-guest-count">{{ $travellerCount }} of {{ $booking->guests }} guests</span>
        </div>
        <p class="admin-document-readiness" aria-live="polite">
            Documents: {{ $receivedDocumentCount }} of {{ $travellerCount }} received
        </p>
        @forelse($travellers as $traveller)
            <article class="admin-traveller-card">
                <div class="admin-traveller-heading">
                    <div><strong>{{ $traveller['name'] }}</strong> <span>Traveller {{ $traveller['number'] }} of {{ $traveller['total'] }}</span></div>
                    @if($traveller['isLead'])<span class="admin-traveller-tag">Lead booker</span>@endif
                </div>
                <dl>
                    <div><dt>Date of birth</dt><dd>{{ $traveller['dob'] ?? '—' }}@if($traveller['age'] !== null) (age {{ $traveller['age'] }})@endif</dd></div>
                    <div><dt>Nationality</dt><dd>{{ $traveller['nationality'] ?: '—' }}</dd></div>
                    <div><dt>Passport</dt><dd>{{ $traveller['passport'] ?? '—' }}</dd></div>
                    @if($traveller['additionalLeadPassport'])<div><dt>Lead booker passport</dt><dd>{{ $traveller['additionalLeadPassport'] }}</dd></div>@endif
                    <div>
                        <dt>Passport expiry</dt>
                        <dd><span class="admin-expiry-badge {{ $traveller['expiryConcern'] || $traveller['expiryMissing'] ? 'is-warning' : 'is-valid' }}">{{ $traveller['passportExpiry'] ?? 'Not provided' }}</span></dd>
                    </div>
                </dl>
                @if($traveller['expiryConcern'])
                    <p class="admin-traveller-warning">Passport is valid for less than 6 months after departure. Ask the traveller to renew before the trip.</p>
                @elseif($traveller['expiryMissing'])
                    <p class="admin-traveller-warning">Passport expiry date is not recorded.</p>
                @endif
                <div class="admin-document-row">
                    <span class="admin-document-status {{ $traveller['hasDocument'] ? 'is-received' : 'is-missing' }}">{{ $traveller['hasDocument'] ? 'Document received' : 'Document missing' }}</span>
                    @if($traveller['documentUrl'])
                        <a class="text-link" href="{{ $traveller['documentUrl'] }}">Download passport or ID image</a>
                    @else
                        <span class="muted">No passport or ID image is attached.</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="muted">No traveller details were provided.</p>
        @endforelse
    </section>
</div>
@endsection

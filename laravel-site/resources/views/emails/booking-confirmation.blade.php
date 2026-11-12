@component('mail::message')
# Your booking details

Thank you for booking with TravelWithYou. Your order **{{ $orderReference }}** has been received.

@component('mail::panel')
**Payment status:** {{ $paymentStatus }}  
**Order total:** {{ fmt_rm($bookings->sum(fn ($booking) => $booking->subtotal - $booking->discount + $booking->taxes)) }}
@endcomponent

@foreach($bookings as $booking)
## {{ $booking->package->name }}

**Booking reference:** {{ $booking->reference }}  
**Departure:** {{ fmt_date($booking->departure_date) }}  
**Duration:** {{ $booking->package->days }} days  
**Travellers:** {{ $booking->guests }}

@if($booking->package->departure_port || $booking->package->destination_port)
**Route:** {{ $booking->package->departure_port ?: '—' }} → {{ $booking->package->destination_port ?: '—' }}
@endif
@if($booking->package->ship)
**Ship:** {{ $booking->package->ship }}
@endif

@php
    $bookingDetail = $booking->guest_details['booking_detail'] ?? [];
    $rooms = $bookingDetail['rooms'] ?? [];
@endphp
@if($rooms !== [])
**Rooms:** {{ collect($rooms)->map(fn ($room, $index) => 'Room '.($index + 1).(!empty($room['type']) ? ' — '.$room['type'] : ''))->join(', ') }}
@endif
**Booking total:** {{ fmt_rm($booking->subtotal - $booking->discount + $booking->taxes) }}

@endforeach

The itinerary PDF for each trip is attached to this email. Please keep this email and the attachments for your records. Our travel team will contact you if any further information is needed.

Thanks,<br>
{{ config('app.name', 'TravelWithYou') }}
@endcomponent

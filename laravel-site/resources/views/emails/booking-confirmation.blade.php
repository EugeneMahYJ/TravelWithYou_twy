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

@php($dailyPlan = $booking->package->dailyPlan())
@if($dailyPlan !== [])
### Itinerary
@foreach($dailyPlan as $index => $day)
**Day {{ $index + 1 }} · {{ $day['place'] }}**  
@if($day['activities']){{ $day['activities'] }}@endif
@if($day['transport'])
  
Transport: {{ $day['transport'] }}
@endif

@endforeach
@endif
@endforeach

Please keep this email for your records. Our travel team will contact you if any further information is needed.

Thanks,<br>
{{ config('app.name', 'TravelWithYou') }}
@endcomponent

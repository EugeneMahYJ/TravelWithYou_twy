<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { color: #0f2238; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2 { font-family: DejaVu Serif, serif; }
        .reference { border: 1px dashed #0f2238; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 10px 6px; border-bottom: 1px solid #ccc; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>TravelWithYou booking invoice</h1>
    <p class="reference">Booking reference: <strong>{{ $orderReference }}</strong></p>
    <table>
        <thead><tr><th>Package</th><th>Departure</th><th>Guests</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach($bookings as $booking)
                <tr>
                    <td>{{ $booking->package->name }}<br>{{ $booking->package->country }}</td>
                    <td>{{ fmt_date($booking->departure_date) }}</td>
                    <td>{{ $booking->guests }}</td>
                    <td class="right">{{ fmt_rm($booking->subtotal - $booking->discount + $booking->taxes) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2 class="right">Total incl. taxes: {{ fmt_rm($totalPaid) }}</h2>
</body>
</html>

@extends('admin.layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Dashboard</p><h1>Hello, {{ auth()->user()->name }}.</h1><p>A clear view of what is happening across your journeys.</p></div>
    <form method="get" class="admin-month-select"><label for="dashboard-month">Month</label><select id="dashboard-month" name="month" onchange="this.form.submit()">@foreach($months as $month)<option value="{{ $month->format('Y-m') }}" @selected($month->isSameMonth($selectedMonth))>{{ $month->format('F Y') }}</option>@endforeach</select></form>
</div>
<div class="admin-dashboard-grid">
    <article class="admin-stat-card"><div class="admin-stat-top"><span>Users</span><span>♙</span></div><strong>{{ number_format($userTotal) }}</strong><p>+{{ number_format($newUsers) }} this month</p><a href="{{ route('admin.users') }}">View users →</a></article>
    <article class="admin-stat-card"><div class="admin-stat-top"><span>Bookings</span><span>▤</span></div><strong>{{ number_format($bookingTotal) }}</strong><p>{{ number_format($newBookings) }} new</p><a href="{{ route('admin.bookings') }}">View bookings →</a></article>
    <article class="admin-stat-card admin-sales-card"><div class="admin-stat-top"><span>Sales</span><span>{{ $salesChange === null ? '—' : ($salesChange >= 0 ? '+' : '').$salesChange.'%' }} vs previous month</span></div><strong>{{ fmt_rm($sales->last()['amount'] ?? 0) }}</strong>
        <div class="admin-sales-bars" aria-label="Sales over the last six months">
            @php($maxSales = max(1, $sales->max('amount')))
            @foreach($sales as $point)<a class="admin-sales-bar-wrap" href="{{ route('admin.dashboard', ['month' => $point['date']->format('Y-m')]) }}" aria-label="{{ $point['date']->format('F Y') }}: {{ fmt_rm($point['amount']) }}"><span class="admin-sales-bar" style="height: {{ max(4, (int) round($point['amount'] / $maxSales * 100)) }}%"></span><small>{{ $point['date']->format('M') }}</small></a>@endforeach
        </div><p>Confirmed booking value · {{ $selectedMonth->format('F Y') }}</p>
    </article>
</div>
<div class="admin-dashboard-grid admin-dashboard-grid-lower">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Expiring packages</h2><a href="{{ route('admin.packages', ['status' => 'Expiring']) }}">View packages →</a></div>
        <div class="admin-expiry-stats"><div><strong>{{ $expiry['expired'] }}</strong><span>Expired in last 7 days</span></div><div><strong>{{ $expiry['expiring'] }}</strong><span>Expiring in next 7 days</span></div><div><strong>{{ $expiry['next']?->format('j M Y') ?? '—' }}</strong><span>Next to expire</span></div></div>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Trending searches</h2><span>Top 5 this month</span></div>
        @forelse($trending as $search)<div class="admin-trend-row"><span>{{ $search->query }}</span><span class="admin-trend-track"><i style="width: {{ max(4, (int) round($search->searches / max(1, $trending->max('searches')) * 100)) }}%"></i></span><strong>{{ $search->searches }}</strong></div>@empty<p class="muted">Search trends will appear here when travellers search.</p>@endforelse
    </section>
</div>
<div class="admin-dashboard-grid admin-dashboard-grid-lower">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Recent bookings</h2><a href="{{ route('admin.bookings') }}">View all →</a></div>
        @if($recentBookings->isEmpty())<p class="muted">Bookings will appear here when travellers complete checkout.</p>@else
        <div class="table-scroll"><table><thead><tr><th>Ref</th><th>Customer</th><th>Package</th><th>Departure</th><th>Total</th><th>Status</th></tr></thead><tbody>
            @foreach($recentBookings as $booking)<tr><td><a href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->reference }}</a></td><td>{{ $booking->user?->name ?? data_get($booking->guest_details, 'name', 'Guest') }}</td><td>{{ $booking->package?->name ?? '—' }}</td><td>{{ $booking->departure_date->format('j M Y') }}</td><td>{{ fmt_rm($booking->total) }}</td><td><span class="status-pill">{{ $booking->status }}</span></td></tr>@endforeach
        </tbody></table></div>@endif
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Needs attention</h2></div>
        <div class="admin-attention-list">@forelse($attention as $item)<a class="admin-notification-item admin-notification-{{ $item['severity'] }}" href="{{ $item['url'] }}"><span><strong>{{ $item['title'] }}</strong><small>{{ $item['detail'] }}</small>@if($item['time'])<time>{{ $item['time'] }}</time>@endif</span><b>{{ $item['count'] }} <span aria-hidden="true">→</span></b></a>@empty<p class="muted">You're all caught up.</p>@endforelse</div>
    </section>
</div>
@endsection

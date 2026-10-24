@extends('admin.layouts.admin')
@section('title', 'Search')
@section('content')
<div class="admin-heading"><div><p class="eyebrow accent">Workspace search</p><h1>Search results</h1><p>Matches for “{{ request('q') }}”.</p></div></div>
<section class="admin-panel"><div class="admin-panel-heading"><h2>Ships · {{ $ships->count() }}</h2><a href="{{ route('admin.ships', ['q' => request('q')]) }}">View ship results →</a></div>
    <div class="table-scroll"><table><thead><tr><th>Ship</th><th>Class</th><th>Capacity</th><th>Cabins</th><th></th></tr></thead><tbody>@forelse($ships as $ship)<tr><td>{{ $ship->name }}</td><td>{{ $ship->class_name }}</td><td>{{ number_format($ship->capacity) }}</td><td>{{ $ship->cabins_count }}</td><td><a class="table-link" href="{{ route('admin.ships.edit', $ship) }}">Edit</a></td></tr>@empty<tr><td colspan="5">No matching ships.</td></tr>@endforelse</tbody></table></div>
</section>
<section class="admin-panel"><div class="admin-panel-heading"><h2>Packages · {{ $packages->count() }}</h2><a href="{{ route('admin.packages', ['q' => request('q')]) }}">View package results →</a></div>
    <div class="table-scroll"><table><thead><tr><th>Package</th><th>Country</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>@forelse($packages as $package)<tr><td>{{ $package->name }}</td><td>{{ $package->country }}</td><td>{{ $package->type }}</td><td><span class="status-pill">{{ $package->published ? 'Published' : 'Draft' }}</span></td><td><a class="table-link" href="{{ route('admin.packages.edit', $package) }}">Edit</a></td></tr>@empty<tr><td colspan="5">No matching packages.</td></tr>@endforelse</tbody></table></div>
</section>
<section class="admin-panel"><div class="admin-panel-heading"><h2>Bookings · {{ $bookings->count() }}</h2><a href="{{ route('admin.bookings', ['q' => request('q')]) }}">View booking results →</a></div>
    <div class="table-scroll"><table><thead><tr><th>Ref</th><th>Customer</th><th>Package</th><th>Departure</th><th>Status</th><th></th></tr></thead><tbody>@forelse($bookings as $booking)<tr><td>{{ $booking->reference }}</td><td>{{ $booking->user?->name ?? data_get($booking->guest_details, 'name', 'Guest') }}</td><td>{{ $booking->package?->name }}</td><td>{{ $booking->departure_date->format('j M Y') }}</td><td>{{ $booking->status }}</td><td><a class="table-link" href="{{ route('admin.bookings.show', $booking) }}">View</a></td></tr>@empty<tr><td colspan="6">No matching bookings.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection

@extends('admin.layouts.admin')
@section('title', 'Ships')
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Fleet</p><h1>Ships</h1><p>Manage the ships shown across the website and in cruise packages.</p></div>
    <a class="button button-primary" href="{{ route('admin.ships.create') }}">New ship</a>
</div>
<section class="admin-panel">
    <form class="admin-filter-form" method="get" action="{{ route('admin.ships') }}">
        <label><span class="visually-hidden">Search ships</span><input type="search" name="q" placeholder="Search ships…" value="{{ request('q') }}"></label>
        <button class="button button-ghost" type="submit">Search</button>
        @if(request()->filled('q'))<a class="text-link" href="{{ route('admin.ships') }}">Clear</a>@endif
    </form>
    <div class="table-scroll"><table><thead><tr><th>Ship</th><th>Class</th><th>Capacity</th><th>Crew</th><th>Rating</th><th>Built</th><th>Cabins</th><th>Cruises</th><th></th></tr></thead><tbody>
        @forelse($ships as $ship)
            <tr>
                <td><div class="table-package"><img src="{{ asset_img($ship->image) }}" alt=""><strong>{{ $ship->name }}</strong><small>{{ $ship->slug }}</small></div></td>
                <td>{{ $ship->class_name }}</td>
                <td>{{ number_format($ship->capacity) }}</td>
                <td>{{ number_format($ship->crew) }}</td>
                <td>{{ number_format($ship->rating, 1) }} ★</td>
                <td>{{ $ship->year }}</td>
                <td>{{ $ship->cabins_count }}</td>
                <td>{{ $cruiseCounts[$ship->name] ?? 0 }}</td>
                <td>
                    <a class="table-link" href="{{ route('ships.show', ['ship' => $ship->slug]) }}" target="_blank" rel="noopener">View</a>
                    <a class="table-link" href="{{ route('admin.ships.edit', $ship) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9">No ships match this search.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection

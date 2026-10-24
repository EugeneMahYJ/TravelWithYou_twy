@extends('admin.layouts.admin')
@section('title', 'Packages')
@section('content')
<div class="admin-heading"><div><p class="eyebrow accent">Catalogue</p><h1>Packages</h1><p>Search and manage your travel packages.</p></div><a class="button button-primary" href="{{ route('admin.packages.create') }}">New package</a></div>
<section class="admin-panel">
    <form class="admin-filter-form" method="get" action="{{ route('admin.packages') }}">
        <label><span class="visually-hidden">Search packages</span><input type="search" name="q" placeholder="Search packages…" value="{{ $filters['q'] ?? '' }}"></label>
        <label><span class="visually-hidden">Region</span><select name="region"><option value="">All regions</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(($filters['region'] ?? '') === $region)>{{ $region }}</option>@endforeach</select></label>
        <label><span class="visually-hidden">Country</span><select name="country"><option value="">All countries</option>@foreach($countries as $country)<option value="{{ $country }}" @selected(($filters['country'] ?? '') === $country)>{{ $country }}</option>@endforeach</select></label>
        <label><span class="visually-hidden">Status</span><select name="status">@foreach(['All', 'Published', 'Expiring', 'Draft'] as $status)<option @selected(($filters['status'] ?? 'All') === $status)>{{ $status }}</option>@endforeach</select></label>
        <button class="button button-primary" type="submit">Filter</button>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Package</th><th>Type</th><th>Region</th><th>Country</th><th>Days</th><th>From</th><th>Departures</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($packages as $package)
            <tr><td><div class="table-package"><img src="{{ asset_img($package->image) }}" alt=""><strong>{{ $package->name }}</strong></div></td><td>{{ $package->type }}</td><td>{{ $package->region }}</td><td>{{ $package->country }}</td><td>{{ $package->days }}</td><td>{{ fmt_rm($package->price_from) }}</td><td>{{ count($package->departures ?? []) }}</td><td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($package->admin_status) }}">{{ $package->admin_status }}</span></td><td><a class="table-link" href="{{ route('admin.packages.edit', $package) }}">Edit</a></td></tr>
        @empty<tr><td colspan="9">No packages match these filters.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection

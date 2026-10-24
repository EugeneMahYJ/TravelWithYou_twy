@extends('admin.layouts.admin')
@section('title', $deal->exists ? 'Edit deal' : 'New deal')
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Deals</p><h1>{{ $deal->exists ? 'Edit deal' : 'New deal' }}</h1><p>Set the offer details and availability.</p></div>
</div>
<section class="admin-panel admin-deal-editor">
    @include('admin.deals.partials.form')
</section>
@endsection

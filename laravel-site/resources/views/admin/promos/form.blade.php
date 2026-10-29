@extends('admin.layouts.admin')
@section('title', $promoCode->exists ? 'Edit promo code' : 'New promo')
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Deals · Promo codes</p><h1>{{ $promoCode->exists ? 'Edit promo code' : 'New promo' }}</h1><p>Create a promo code and choose its discount and eligible packages.</p></div>
</div>
<section class="admin-panel admin-deal-editor">
    @include('admin.promos.partials.form')
</section>
@endsection

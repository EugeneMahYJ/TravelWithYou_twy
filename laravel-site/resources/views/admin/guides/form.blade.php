@extends('admin.layouts.admin')
@section('title', $guide->exists ? 'Edit guide' : 'New guide')
@section('content')
<div class="admin-heading"><div><p class="eyebrow accent">Travel guides</p><h1>{{ $guide->exists ? 'Edit guide' : 'New guide' }}</h1><p>Write a guide for the TravelWithYou journal.</p></div><a class="text-link" href="{{ route('admin.guides') }}">← Guides</a></div>
<form class="admin-editor-form" method="post" action="{{ $guide->exists ? route('admin.guides.update', $guide) : route('admin.guides.store') }}">@csrf @if($guide->exists) @method('put') @endif
    <div class="form-grid"><label><span>Title</span><input name="title" value="{{ old('title', $guide->title) }}" required>@error('title')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Slug</span><input name="slug" value="{{ old('slug', $guide->slug) }}" placeholder="Generated from title">@error('slug')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Topic</span><input name="category" value="{{ old('category', $guide->category) }}" required></label><label><span>Read time</span><input name="read_time" value="{{ old('read_time', $guide->read_time) }}" placeholder="5 min read"></label>
        <label class="form-wide"><span>Image URL or asset name</span><div class="admin-asset-field"><input id="guide-cover-image" name="image" value="{{ old('image', $guide->image) }}"><button class="button button-ghost" type="button" data-asset-picker-trigger data-asset-picker-kind="image" data-asset-picker-target="#guide-cover-image">Browse library</button></div></label>
        <label class="form-wide"><span>Excerpt</span><textarea name="excerpt" rows="3">{{ old('excerpt', $guide->excerpt) }}</textarea></label>
        <label class="form-wide"><span>Body</span><textarea name="body" rows="16" required>{{ old('body', $guide->body) }}</textarea>@error('body')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label class="check-label form-wide"><input type="checkbox" name="published" value="1" @checked(old('published', $guide->exists ? $guide->published : true))><span>Published</span></label>
    </div><div class="form-actions"><button class="button button-primary" type="submit">Save guide</button><a class="text-link" href="{{ route('admin.guides') }}">Cancel</a></div>
</form>
@include('admin.partials.asset-picker', ['mediaAssets' => $mediaAssets])
@endsection

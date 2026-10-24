@extends('layouts.app')
@section('title', $post ? $post->title.' — Travel guides' : 'Travel guides — TravelWithYou')
@section('content')
@if($post)
    <article class="wrap guide-article">
        <a class="guide-back" href="{{ route('blog') }}">← Travel guides</a>
        <p class="guide-meta">{{ $post->category }} · {{ $post->read_time }} read</p>
        <h1>{{ $post->title }}</h1>
        <img class="guide-hero-image" src="{{ asset_img($post->image) }}" alt="{{ $post->title }}">
        <p>{{ $post->body }}</p>
    </article>
@else
    <x-page-head kicker="Travel guides" title="Field notes." />
    <section class="wrap guide-grid">
        @foreach($posts as $entry)
            <a class="guide-card" href="{{ route('blog.show', ['slug' => $entry->slug]) }}">
                <div><img src="{{ asset_img($entry->image) }}" alt="{{ $entry->title }}" loading="lazy"></div>
                <p>{{ $entry->category }} · {{ $entry->read_time }} read</p>
                <h2>{{ $entry->title }}</h2>
            </a>
        @endforeach
    </section>
@endif
@endsection

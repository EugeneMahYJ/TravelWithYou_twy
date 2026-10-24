@extends('layouts.app')
@section('title', 'FAQ — TravelWithYou')
@section('content')
<x-page-head kicker="FAQ" title="Questions, answered." />
<section class="wrap faq-list faq-port">
    @foreach($faqs as $faq)
        <details name="faq" @if($loop->first) open @endif>
            <summary>{{ $faq->question }}</summary>
            <p>{{ $faq->answer }}</p>
        </details>
    @endforeach
    <p class="faq-help">Still need help? <a href="{{ route('contact') }}">Contact us</a>.</p>
</section>
@endsection

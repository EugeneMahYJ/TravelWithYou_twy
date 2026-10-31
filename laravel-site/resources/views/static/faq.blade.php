@extends('layouts.app')
@section('title', 'FAQ — TravelWithYou')
@section('content')
<header class="faq-hero paper-grain">
    <div class="wrap faq-hero-inner">
        <p class="eyebrow accent">FAQ</p>
        <h1>Good questions. Thoughtful answers.</h1>
        <p class="faq-hero-description">Everything you need to know before planning your next trip with us. Browse by topic or open a question to find the details.</p>
        <span class="faq-hero-mark" aria-hidden="true"></span>
    </div>
</header>
<main class="wrap faq-layout">
    <aside class="faq-sidebar">
        <p class="faq-side-title">Browse topics</p>
        <nav class="faq-topic-nav" aria-label="FAQ topics">
            @foreach($faqGroups as $groupIndex => $group)
                <a href="#faq-category-{{ $groupIndex }}">{{ $group['title'] }}</a>
            @endforeach
        </nav>
        <section class="faq-help">
            <span class="faq-help-icon" aria-hidden="true">?</span>
            <p>Still need help?</p>
            <a href="{{ route('contact') }}">Contact us</a>
        </section>
    </aside>
    <div class="faq-content">
    @foreach($faqGroups as $groupIndex => $group)
        <section class="faq-category">
            <header class="faq-category-heading" id="faq-category-{{ $groupIndex }}">
                <h2>{{ $group['title'] }}</h2>
            </header>
            <div class="faq-items">
            @foreach($group['items'] as $faq)
                <details class="faq-item" name="faq">
                    <summary>
                        <span class="faq-question">{{ $faq->question }}</span>
                        <span class="faq-toggle" aria-hidden="true"></span>
                    </summary>
                    <p class="faq-answer">{{ $faq->answer }}</p>
                </details>
            @endforeach
            </div>
        </section>
    @endforeach
    </div>
</main>
@endsection

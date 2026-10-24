@props(['kicker', 'title', 'lead' => null])

<header class="page-head paper-grain">
    <div class="wrap">
        <p class="eyebrow accent">{{ $kicker }}</p>
        <h1>{{ $title }}</h1>
        @if($lead)
            <p class="page-intro">{{ $lead }}</p>
        @elseif($slot->isNotEmpty())
            <div class="page-intro">{{ $slot }}</div>
        @endif
    </div>
</header>

@props(['number', 'title'])

<div class="section-heading">
    <div>
        <span class="section-number">{{ $number }}</span>
        <h2>{{ $title }}</h2>
    </div>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @endif
</div>

@props(['value', 'count' => null])

<span class="rating" aria-label="Rated {{ number_format((float) $value, 1) }} out of 5{{ $count !== null ? ', '.$count.' reviews' : '' }}">
    <span aria-hidden="true">★</span>
    {{ number_format((float) $value, 1) }}
    @if($count !== null)
        <small>({{ number_format((int) $count) }})</small>
    @endif
</span>

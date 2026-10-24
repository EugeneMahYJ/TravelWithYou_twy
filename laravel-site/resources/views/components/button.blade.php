@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php($buttonClass = 'button button-'.$variant)

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $buttonClass]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $buttonClass]) }}>{{ $slot }}</button>
@endif

@props(['type' => 'info'])

<div {{ $attributes->class(['notice', 'notice-'.$type]) }}>
    {{ $slot }}
</div>

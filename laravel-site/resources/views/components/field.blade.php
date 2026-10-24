@props(['name', 'label', 'hint' => null])

<div {{ $attributes->class(['account-field']) }}>
    <label for="{{ $name }}"><span>{{ $label }}</span>{{ $slot }}</label>
    @if($hint)
        <small class="account-field-hint">{{ $hint }}</small>
    @endif
    @error($name)
        <small class="field-error">{{ $message }}</small>
    @enderror
</div>

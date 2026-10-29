@props(['name', 'label', 'hint' => null])

<div {{ $attributes->class(['account-field']) }}>
    <label for="{{ $name }}"><span>{{ $label }}</span>{{ $slot }}</label>
    @if($hint)
        <small class="account-field-hint">{{ $hint }}</small>
    @endif
    @foreach($errors->get($name) as $message)
        <small class="field-error">{{ $message }}</small>
    @endforeach
</div>

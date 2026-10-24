@props(['name', 'type' => 'text', 'value' => null, 'placeholder' => null, 'autocomplete' => null, 'disabled' => false, 'required' => false])

<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    value="{{ old($name, $value) }}"
    @if($placeholder) placeholder="{{ $placeholder }}" @endif
    @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
    @disabled($disabled)
    @required($required)
    {{ $attributes->class(['account-input', 'is-invalid' => $errors->has($name)]) }}
>

{{-- Text-like input with label, help and error. Extra attributes go to the <input>. --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'bag' => 'default'])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @required($required)
           {{ $attributes->except('class')->merge(['class' => 'field']) }}>
    @if ($help)
        <p class="field-help">{{ $help }}</p>
    @endif
    @error($name, $bag)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

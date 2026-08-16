@props(['name', 'label' => null, 'type' => 'text', 'value' => null])
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $name }}" class="label">{{ $label }}</label>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
           value="{{ old($name, $value) }}"
           {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>

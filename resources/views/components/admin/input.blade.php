@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'icon' => null,
    'hint' => null,
    'error' => true,
])

@php
    $resolvedValue = old($name, $value);
    $hasError = $error && $errors->has($name);
    $controlId = $attributes->get('id', $name);
    $descriptionId = $controlId.'-description';
@endphp

<div class="admin-field">
    @if($label)
        <label for="{{ $controlId }}" class="admin-field-label">
            {{ $label }}
            @if($required)
                <span class="text-red-400 ml-0.5">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[color:var(--admin-muted)] text-xs">
                <i class="{{ $icon }}"></i>
            </div>
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $controlId }}"
            value="{{ $resolvedValue }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            @if($hasError) aria-invalid="true" @endif
            @if(($hasError || $hint) && ! $attributes->has('aria-describedby')) aria-describedby="{{ $descriptionId }}" @endif
            {{ $attributes->except(['id'])->class(['admin-control', 'admin-control--invalid' => $hasError, 'admin-control--leading-icon' => $icon]) }}
        >
    </div>

    @if($hasError)
        <p id="{{ $descriptionId }}" class="admin-field-error">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first($name) }}
        </p>
    @elseif($hint)
        <p id="{{ $descriptionId }}" class="admin-field-hint">{{ $hint }}</p>
    @endif
</div>

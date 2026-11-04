@props([
    'name',
    'label' => null,
    'value' => null,
    'options' => [],
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'hint' => null,
    'error' => true,
])

@php
    $resolvedValue = (string) old($name, $value);
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

    <select
        name="{{ $name }}"
        id="{{ $controlId }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" @endif
        @if(($hasError || $hint) && ! $attributes->has('aria-describedby')) aria-describedby="{{ $descriptionId }}" @endif
        {{ $attributes->except(['id'])->class(['admin-control', 'admin-control--invalid' => $hasError]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if(!empty($options))
            @foreach($options as $val => $optLabel)
                <option value="{{ $val }}" {{ (string)$val === $resolvedValue ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        @endif

        {{ $slot }}
    </select>

    @if($hasError)
        <p id="{{ $descriptionId }}" class="admin-field-error">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first($name) }}
        </p>
    @elseif($hint)
        <p id="{{ $descriptionId }}" class="admin-field-hint">{{ $hint }}</p>
    @endif
</div>

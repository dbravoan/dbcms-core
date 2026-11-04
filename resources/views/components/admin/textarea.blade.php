@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false, 'hint' => null])
@php
    $controlId = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $descriptionId = $controlId.'-description';
    $describedBy = trim((string) $attributes->get('aria-describedby', '').(($hasError || $hint) ? ' '.$descriptionId : ''));
@endphp
<div class="admin-field">
    @if($label)<label for="{{ $controlId }}" class="admin-field-label">{{ $label }} @if($required)<span class="text-red-400">*</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $controlId }}" rows="{{ $rows }}" @if($required) required @endif @if($hasError) aria-invalid="true" @endif @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif {{ $attributes->except(['id', 'aria-describedby'])->class(['admin-control', 'admin-control--invalid' => $hasError]) }}>{{ old($name, $value) }}</textarea>
    @if($hasError)<p id="{{ $descriptionId }}" class="admin-field-error">{{ $errors->first($name) }}</p>@elseif($hint)<p id="{{ $descriptionId }}" class="admin-field-hint">{{ $hint }}</p>@endif
</div>

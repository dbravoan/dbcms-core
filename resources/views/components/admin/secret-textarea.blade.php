@props(['name', 'label', 'hint' => null, 'rows' => 5, 'configured' => false])

<div class="admin-field">
    <label for="{{ $name }}" class="admin-field-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" autocomplete="off" spellcheck="false" aria-describedby="{{ $name }}-hint" @if($errors->has($name)) aria-invalid="true" @endif {{ $attributes->class(['admin-control', 'admin-control--invalid' => $errors->has($name)]) }}></textarea>
    <p id="{{ $name }}-hint" class="admin-field-hint">{{ $hint }} @if($configured)La credencial está guardada y no se muestra.@endif</p>
    @error($name)<p class="admin-field-error">{{ $message }}</p>@enderror
</div>

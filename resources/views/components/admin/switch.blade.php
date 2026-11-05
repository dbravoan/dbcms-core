@props([
    'name' => null,
    'value' => '1',
    'checked' => false,
    'label' => null,
    'hint' => null,
    'size' => 'md',
    'disabled' => false,
    'id' => null,
])

@php
    /**
     * Switch (toggle) compartido del backoffice.
     *
     * Resuelve dos problemas reales: estado ilegible (antes eran checkboxes con
     * `text-[#00FF99]`, que NO pinta el check) y accesibilidad (un checkbox sin
     * `role="switch"`/`aria-checked` no comunica si está activo).
     *
     * Usa clases semánticas (`.admin-switch`) para que cada tema del panel lo
     * pinte con su paleta sin reescribir utilidades de Tailwind con !important.
     */
    $switchId = $id ?? 'switch-'.uniqid();
    $isChecked = filter_var($checked, FILTER_VALIDATE_BOOLEAN);
    $dimensions = $size === 'sm' ? 'h-4 w-7' : 'h-5 w-9';
    $knob = $size === 'sm' ? 'h-3 w-3' : 'h-4 w-4';
@endphp

<label for="{{ $switchId }}"
       @class([
           'admin-switch-field inline-flex items-start gap-2.5',
           'cursor-pointer select-none' => ! $disabled,
           'opacity-50 cursor-not-allowed' => $disabled,
       ])>
    <span class="relative inline-flex shrink-0 items-center">
        <input id="{{ $switchId }}"
               type="checkbox"
               role="switch"
               @if($name) name="{{ $name }}" @endif
               value="{{ $value }}"
               @checked($isChecked)
               @disabled($disabled)
               {{ $attributes->except(['class'])->merge(['class' => 'admin-switch peer sr-only'.($size === 'sm' ? ' admin-switch--sm' : '')]) }}>

        {{-- Pista visual: el propio switch. `peer-checked` es del núcleo Tailwind,
             así que ningún tema puede romperlo. --}}
        <span class="admin-switch-track {{ $dimensions }} rounded-full transition-colors" aria-hidden="true"></span>

        <span class="admin-switch-knob {{ $knob }} absolute left-0.5 rounded-full transition-transform" aria-hidden="true"></span>
    </span>

    @if($label !== null || $hint !== null)
        <span class="min-w-0">
            @if($label !== null)
                <span class="admin-switch-label block text-xs font-bold">{{ $label }}</span>
            @endif
            @if($hint !== null)
                <span class="admin-switch-hint block text-[10px]">{{ $hint }}</span>
            @endif
        </span>
    @endif
</label>

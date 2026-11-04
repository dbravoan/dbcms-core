@props([
    'variant' => 'neutral',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'active' => false,
    // Expresión Alpine opcional que decide el estado activo en el cliente
    // (p. ej. `block.columns_layout === '2'`). Los atributos de componente se
    // evalúan en PHP, así que una expresión de Alpine debe llegar como string.
    'alpineActive' => null,
    'disabled' => false,
    'title' => null,
])

@php
    /**
     * Botón compartido del backoffice con estados garantizados.
     *
     * El hover estaba perdiendo el icono en el tema termoCMS: su hoja de estilos
     * reescribe utilidades de Tailwind (`[class*="bg-[#00FF99]/"]`, `button.bg-*`)
     * con !important y sin `:hover`, así que un botón con icono teal sobre fondo
     * teal sólido acababa teal sobre teal claro. Aquí los estados se exponen con
     * clases semánticas (`.admin-btn--*`), que cada tema pinta en su paleta; el
     * color del icono se define en el mismo bloque, por lo que no puede perderse.
     */
    $tag = $href ? 'a' : 'button';
    $variants = [
        'primary' => 'admin-btn--primary',
        'neutral' => 'admin-btn--neutral',
        'ghost' => 'admin-btn--ghost',
        'danger' => 'admin-btn--danger',
        'warning' => 'admin-btn--warning',
        'success' => 'admin-btn--success',
        'icon' => 'admin-btn--icon',
    ];
    $variantClass = $variants[$variant] ?? $variants['neutral'];
    $sizeClass = match ($size) {
        'sm' => 'admin-btn--sm',
        'lg' => 'admin-btn--lg',
        default => '',
    };
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    @if($title) title="{{ $title }}" @endif
    @disabled($disabled && ! $href)
    @if($href && $disabled) aria-disabled="true" tabindex="-1" @endif
    @if($alpineActive)
        {{-- Estado activo resuelto en el cliente (expresión Alpine). --}}
        x-bind:class="{ 'admin-btn--active': {{ $alpineActive }} }"
    @endif
    @class([
        'admin-btn',
        'admin-btn--active' => $active && ! $alpineActive,
        $variantClass,
        $sizeClass,
        'admin-btn--disabled' => $disabled,
        $attributes->get('class'),
    ])
    {{ $attributes->except(['class']) }}>
    @if($icon)
        <i class="{{ $icon }} admin-btn__icon" aria-hidden="true"></i>
    @endif

    @if(trim((string) $slot) !== '')
        <span class="admin-btn__label">{{ $slot }}</span>
    @endif
</{{ $tag }}>

@props([
    'variant' => 'default', // default|neutral, market|code, brand, success|positive, warning, danger, purple|core, info|sky
    'size' => 'sm',        // sm, md
    'icon' => null,
])

@php
    $variantKey = match(strtolower((string) $variant)) {
        'market', 'code' => 'market',
        'brand' => 'brand',
        'success', 'positive', 'published', 'active' => 'success',
        'warning', 'pending' => 'warning',
        'danger', 'error', 'revoked', 'failed', 'out_of_stock' => 'danger',
        'purple', 'core' => 'purple',
        'info', 'sky', 'blue' => 'info',
        default => 'default',
    };

    $colorClass = match($variantKey) {
        'market' => 'admin-chip-market',
        'brand' => 'admin-chip-brand',
        'success' => 'admin-chip-success',
        'warning' => 'admin-chip-warning',
        'danger' => 'admin-chip-danger',
        'purple' => 'admin-chip-purple',
        'info' => 'admin-chip-info',
        default => 'admin-chip-default',
    };
    $sizeClass = $size === 'md' ? 'px-2.5 py-1 text-xs' : 'px-1.5 py-0.5 text-[10px]';
    $baseClass = "admin-chip inline-flex items-center gap-1.5 rounded border font-mono leading-none {$sizeClass} {$colorClass}";
@endphp

<span {{ $attributes->merge(['class' => $baseClass]) }}>
    @if($icon)
        <i class="{{ $icon }} text-[10px]"></i>
    @endif
    {{ $slot }}
</span>

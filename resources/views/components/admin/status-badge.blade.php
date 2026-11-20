@props([
    'active' => null,
    'status' => null,
    'activeText' => null,
    'inactiveText' => null,
])

@php
    $activeText = $activeText ?? __('admin.status.active');
    $inactiveText = $inactiveText ?? __('admin.status.inactive');
    $val = $status !== null ? $status : $active;

    if (is_bool($val) || $val === 1 || $val === '1' || $val === 0 || $val === '0') {
        $isPositive = (bool) $val;
        $state = $isPositive ? 'positive' : 'inactive';
        $label = $isPositive ? $activeText : $inactiveText;
    } else {
        $strVal = strtolower((string) $val);
        $state = match($strVal) {
            'published', 'active', 'paid', 'completed', 'optimal' => 'positive',
            'pending', 'processing', 'warning', 'improving' => 'warning',
            'cancelled', 'failed', 'revoked', 'out_of_stock', 'low' => 'danger',
            'default', 'core', 'system' => 'brand',
            default => 'inactive',
        };
        $label = match($strVal) {
            'published', 'draft', 'active', 'inactive', 'pending', 'processing', 'paid', 'unpaid',
            'completed', 'cancelled', 'failed', 'revoked', 'optimal', 'improving', 'low', 'default' => __('admin.status.'.$strVal),
            default => ucfirst((string) $val),
        };
    }

    $badgeClass = match($state) {
        'positive' => 'admin-badge-positive',
        'warning' => 'admin-badge-warning',
        'danger' => 'admin-badge-danger',
        'brand' => 'admin-badge-brand',
        default => 'admin-badge-default',
    };
    $containerClass = 'admin-badge inline-flex items-center px-2 py-0.5 rounded text-[10px] border font-mono ' . $badgeClass;
@endphp

<span {{ $attributes->merge(['class' => $containerClass]) }}>
    {{ $label }}
</span>

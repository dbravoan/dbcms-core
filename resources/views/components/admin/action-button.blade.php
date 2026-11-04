@props([
    'href' => null,
    'icon' => 'fa-solid fa-circle',
    'tooltip' => '',
    'variant' => 'default',
    'size' => 'md',
])

@php
    $dimClass = match ($size) {
        'sm' => 'w-7 h-7 text-[11px]',
        default => 'w-8 h-8 text-xs',
    };

    $colorClass = match ($variant) {
        'primary' => 'admin-action-btn-primary',
        'view' => 'admin-action-btn-view',
        'danger' => 'admin-action-btn-danger',
        'warning' => 'admin-action-btn-warning',
        default => 'admin-action-btn-default',
    };

    $baseClass = "admin-action-btn group relative inline-flex items-center justify-center rounded-lg transition-all duration-150 cursor-pointer {$dimClass} {$colorClass}";
@endphp

@if($href)
    <a href="{{ $href }}"
       title="{{ $tooltip }}"
       {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="{{ $icon }}"></i>

        @if($tooltip)
            <span class="admin-tooltip pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 transition-opacity">
                <span class="admin-tooltip-text px-2 py-0.5 text-[10px] font-bold rounded shadow-xl whitespace-nowrap bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] font-mono">
                    {{ $tooltip }}
                </span>
                <span class="admin-tooltip-arrow w-1.5 h-1.5 rotate-45 -mt-0.5 bg-[color:var(--admin-surface)] border-r border-b border-[color:var(--admin-border)]"></span>
            </span>
        @endif
    </a>
@else
    <button type="button"
            title="{{ $tooltip }}"
            {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="{{ $icon }}"></i>

        @if($tooltip)
            <span class="admin-tooltip pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 transition-opacity">
                <span class="admin-tooltip-text px-2 py-0.5 text-[10px] font-bold rounded shadow-xl whitespace-nowrap bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] font-mono">
                    {{ $tooltip }}
                </span>
                <span class="admin-tooltip-arrow w-1.5 h-1.5 rotate-45 -mt-0.5 bg-[color:var(--admin-surface)] border-r border-b border-[color:var(--admin-border)]"></span>
            </span>
        @endif
    </button>
@endif

@php
    /**
     * Collapsible navigation toggle. Honours the backoffice navigation settings
     * (`front.nav_toggle_enabled`, `front.nav_toggle_icon`, `front.nav_toggle_desktop`).
     */
    $nav = $frontNav ?? [];
    $icons = [
        'bars' => 'fa-bars',
        'chevron' => 'fa-chevron-down',
        'plus' => 'fa-plus',
        'grid' => 'fa-grip',
        'dots' => 'fa-ellipsis',
    ];
    $faIcon = $icons[$nav['icon'] ?? 'bars'] ?? 'fa-bars';
    $onDesktop = (bool) ($nav['toggleDesktop'] ?? false);
@endphp

@if($nav['toggle'] ?? true)
    <button type="button"
            data-front-nav
            data-nav-toggle
            x-data="{}"
            @click="$store.nav.open = ! $store.nav.open"
            class="{{ $class ?? '' }}{{ $onDesktop ? '' : ' md:hidden' }}"
            :aria-expanded="$store.nav.open.toString()"
            :class="$store.nav.open ? '{{ $activeClass ?? '' }}' : ''"
            aria-label="{{ __('Abrir menú de navegación') }}">
        <i class="fa-solid {{ $faIcon }}"></i>
    </button>
@endif

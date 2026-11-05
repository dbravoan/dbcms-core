@php
    /**
     * Collapsible navigation panel (front themes).
     *
     * Visible on mobile always; on desktop only when the toggle is available
     * there (`front.nav_toggle_desktop`) or the layout is `dropdown`.
     *
     * @var array<int, array{title?: string, url?: string, target_blank?: bool}> $items
     * @var array<int, array{title?: string, url?: string, blank?: bool}> $fallback
     */
    $nav = $frontNav ?? [];
    $onDesktop = (bool) ($nav['toggleDesktop'] ?? false) || ($nav['layout'] ?? 'inline') === 'dropdown';
@endphp

<div data-front-nav
     x-data="{}"
     x-show="$store.nav.open"
     x-cloak
     class="{{ $class ?? '' }}{{ $onDesktop ? '' : ' md:hidden' }}">
    @include('themes::components.nav-items', [
        'items' => $items ?? [],
        'fallback' => $fallback ?? [],
        'class' => $navClass ?? 'flex flex-col',
        'linkClass' => $linkClass ?? 'block py-2',
    ])

    @include('themes::components.nav-admin', [
        'frontNav' => $nav,
        'class' => $adminClass ?? '',
    ])
</div>

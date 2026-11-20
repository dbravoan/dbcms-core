@php
    /**
     * Renders the navigation links (shared by every front theme).
     *
     * @var array<int, array{title?: string, url?: string, target_blank?: bool, children?: array}> $items
     * @var array<int, array{title?: string, url?: string, blank?: bool}> $fallback
     */
    $withTitle = static fn (array $link): bool => (string) ($link['title'] ?? '') !== '';
    $links = collect($items ?? [])->filter($withTitle);

    if ($links->isEmpty()) {
        $links = collect($fallback ?? [])->filter($withTitle);
    }
@endphp

<nav data-front-nav @class([$class ?? '', 'cms-nav-inline' => ! ($inPanel ?? false)])>
    @include('themes::components.nav-entries', ['links' => $links])
</nav>

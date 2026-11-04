@php
    /**
     * Renders the navigation links (shared by every front theme).
     *
     * @var array<int, array{title?: string, url?: string, target_blank?: bool}> $items
     * @var array<int, array{title?: string, url?: string, blank?: bool}> $fallback
     */
    $links = collect($items ?? [])
        ->map(static fn ($item): array => [
            'title' => (string) ($item['title'] ?? ''),
            'url' => (string) ($item['url'] ?? '#'),
            'blank' => (bool) ($item['target_blank'] ?? false),
        ])
        ->filter(static fn (array $link): bool => $link['title'] !== '')
        ->values();

    if ($links->isEmpty()) {
        $links = collect($fallback ?? [])
            ->map(static fn ($item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
                'blank' => (bool) ($item['blank'] ?? false),
            ])
            ->filter(static fn (array $link): bool => $link['title'] !== '')
            ->values();
    }
@endphp

<nav data-front-nav class="{{ $class ?? '' }}">
    @foreach($links as $link)
        <a href="{{ $link['url'] }}"@if($link['blank']) target="_blank" rel="noopener"@endif class="{{ $linkClass ?? '' }}">{{ $link['title'] }}</a>
    @endforeach
</nav>

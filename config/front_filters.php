<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front-office friendly filter URLs
|--------------------------------------------------------------------------
| Content filters, searches and facets are addressed through localized path
| segments instead of query strings, so every combination is a unique,
| shareable, indexable-or-not URL with its own SEO text and hreflang.
|
| Canonical grammar (markerless, "the value speaks for itself"):
|
|   /{market}/{locale}/{module}/{category}/{value}+{value}/{reserved}/{range}…
|
|   /tienda/camisetas/entre-100-200-eur/destacados/precio-asc
|   /blog/tecnologia/ia-geo/laravel+geo/recientes
|   /blog/desde-2026-01-01/hasta-2026-06-30
|
| Resolution order for a bare segment (deterministic, see docs/20):
|   1. reserved tokens (enum options / bool markers)   → e.g. `recientes`
|   2. localized connectors + shapes (dates, ranges)   → e.g. `desde-…`, `entre-…`
|   3. exact term vocabularies (`+` joins values)
|   4. search fallback (only the last segment, opt-in per module)
|
| Valid non-canonical ordering redirects to the canonical form.
| Ambiguous or unsupported syntax resolves to 404.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | SEO / GEO limits (see docs/20)
    |--------------------------------------------------------------------------
    | Combinations that exceed these limits, or that return fewer than
    | `min_results`, are never indexed (they still render, with noindex).
    */
    'limits' => [
        'max_dimensions' => 3,
        'max_values_per_dimension' => 3,
        'max_path_length' => 80,
        'min_results' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Localized dimension qualifiers
    |--------------------------------------------------------------------------
    | dimension key => [locale => marker]. Inline qualifiers disambiguate a
    | bare value when necessary (`etiqueta-laravel`, `3-habitaciones`).
    */
    'markers' => [
        'category' => ['es' => 'categoria', 'en' => 'category', 'ca' => 'categoria'],
        'tag' => ['es' => 'etiqueta', 'en' => 'tag', 'ca' => 'etiqueta'],
        'search' => ['es' => 'buscar', 'en' => 'search', 'ca' => 'cercar'],
        'sort' => ['es' => 'orden', 'en' => 'sort', 'ca' => 'ordre'],
        'price' => ['es' => 'precio', 'en' => 'price', 'ca' => 'preu'],
        'featured' => ['es' => 'destacados', 'en' => 'featured', 'ca' => 'destacats'],
        'date_from' => ['es' => 'desde', 'en' => 'from', 'ca' => 'des-de'],
        'date_to' => ['es' => 'hasta', 'en' => 'to', 'ca' => 'fins-a'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localized connectors (ranges, dates and inline qualifiers)
    |--------------------------------------------------------------------------
    | These words make ranges and dates read naturally in every language:
    | `entre-100-200-eur`, `desde-2026-01-01`, `hasta-200-eur`, `1-3-…`.
    */
    'connectors' => [
        // Range: both bounds / lower only / upper only.
        'between' => ['es' => 'entre', 'en' => 'between', 'ca' => 'entre', 'fr' => 'entre', 'de' => 'zwischen'],
        'from' => ['es' => 'desde', 'en' => 'from', 'ca' => 'des-de', 'fr' => 'depuis', 'de' => 'ab'],
        'to' => ['es' => 'hasta', 'en' => 'to', 'ca' => 'fins-a', 'fr' => 'jusqua', 'de' => 'bis'],
        // Accepted aliases for open ranges.
        'over' => ['es' => 'mas-de', 'en' => 'over', 'ca' => 'mes-de'],
        'under' => ['es' => 'menos-de', 'en' => 'under', 'ca' => 'menys-de'],
        // Term connectors used by modules with preposition facets (`con-lechuga`).
        'with' => ['es' => 'con', 'en' => 'with', 'ca' => 'amb'],
        'without' => ['es' => 'sin', 'en' => 'without', 'ca' => 'sense'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localized range units
    |--------------------------------------------------------------------------
    | dimension key => [locale => unit]. A range may also take its unit from the
    | market currency (`marketCurrency: true` on the dimension).
    */
    'units' => [
        'minutes' => ['es' => 'minutos', 'en' => 'minutes', 'ca' => 'minuts'],
        'price' => ['es' => 'eur', 'en' => 'eur', 'ca' => 'eur'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-value separator
    |--------------------------------------------------------------------------
    | Multiple values use `+` (`/blog/laravel+vue`).
    */

    /*
    |--------------------------------------------------------------------------
    | Cached vocabulary TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'vocabulary_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Automatic discovery (Filtros y búsquedas inbox)
    |--------------------------------------------------------------------------
    | Combinations actually visited are registered (canonical path only, no
    | personal data) and stay `noindex` until the editor activates them.
    */
    'discovery' => [
        'enabled' => env('CMS_FILTER_DISCOVERY', true),
        // At most one write per combination within this window.
        'throttle_minutes' => 10,
        // Reserved for the opt-in "count cached impressions too" mode.
        'track_cached' => false,
        // Only used by the optional prune command.
        'retention_days' => 180,
    ],
];

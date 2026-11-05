<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Models\Market;

/**
 * Builds canonical (and cross-locale) filter paths from a {@see FilterSet}.
 *
 * Canonical form is the **shortest unambiguous** one: values speak for
 * themselves (`/blog/laravel+vue/recientes`, `/tienda/entre-100-200-eur`)
 * because the canonical path is validated by re-parsing it. Only when a bare
 * value would be ambiguous does the builder add the dimension qualifier
 * (`etiqueta-laravel+vue`) and, as a last resort, the explicit marker form.
 *
 * The publisher is deterministic: the same set always produces the same path.
 */
final class FilterUrlBuilder
{
    public function __construct(
        private readonly FilterPathParser $parser,
    ) {}

    /**
     * @param  array<int, FilterDimension>  $dimensions
     */
    public function canonicalPath(FilterSet $set, array $dimensions, string $locale, ?Market $market = null): ?string
    {
        return $this->path($set, $dimensions, $locale, null, $market);
    }

    /**
     * The same filter set expressed in another locale: markers drift away, but
     * values and localized connectors (ranges, dates) are translated, so
     * hreflang points at the true localized URL.
     *
     * @param  array<int, FilterDimension>  $dimensions
     */
    public function translatedPath(
        FilterSet $set,
        array $dimensions,
        string $fromLocale,
        string $toLocale,
        ?Market $market = null,
    ): ?string {
        if ($fromLocale === $toLocale) {
            return $this->canonicalPath($set, $dimensions, $toLocale, $market);
        }

        return $this->path($set, $dimensions, $toLocale, $fromLocale, $market);
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     */
    private function path(
        FilterSet $set,
        array $dimensions,
        string $locale,
        ?string $translateFrom,
        ?Market $market,
    ): ?string {
        $ordered = $this->ordered($dimensions);
        $market = $market ?? $this->market($set);

        // Markerless first; escalate only if it does not round-trip.
        foreach (['bare', 'qualified', 'explicit'] as $mode) {
            $segments = $this->segments($set, $ordered, $locale, $translateFrom, $market, $mode);

            if ($segments === null) {
                return null;
            }

            $candidate = implode('/', array_filter($segments, static fn (string $s): bool => $s !== ''));

            if ($candidate === '') {
                return '';
            }

            $reparsed = $this->parser->parse(
                $set->module,
                $market !== null ? (string) $market->code : $set->market,
                $locale,
                $candidate,
                $dimensions,
            );

            if ($reparsed !== null && $reparsed->signature() === $set->signature()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<int, string>|null
     */
    private function segments(
        FilterSet $set,
        array $dimensions,
        string $locale,
        ?string $translateFrom,
        ?Market $market,
        string $mode,
    ): ?array {
        $segments = [];

        foreach ($dimensions as $dimension) {
            if (! $set->has($dimension->key)) {
                continue;
            }

            // A flag owns a reserved token; it is never qualified.
            if ($dimension->type === 'bool') {
                $segments[] = $dimension->marker($locale);

                continue;
            }

            $value = $this->stringValue($dimension, $set->get($dimension->key), $locale, $translateFrom, $market !== null ? (string) $market->code : $set->market);

            if ($value === null) {
                return null;
            }

            if ($mode === 'explicit' && ! $dimension->implicit) {
                $segments[] = $dimension->marker($locale).'/'.$value;

                continue;
            }

            if ($dimension->type === 'terms') {
                $segments[] = ($mode === 'qualified' && ! $dimension->implicit)
                    ? $dimension->qualifier($locale).'-'.$value
                    : $value;

                continue;
            }

            $segments[] = in_array($dimension->type, ['date', 'range'], true)
                ? $this->shape($dimension, $value, $locale, $market)
                : $value;
        }

        return $segments;
    }

    /**
     * String projection of a filter value (terms are translated and joined).
     */
    private function stringValue(
        FilterDimension $dimension,
        mixed $raw,
        string $locale,
        ?string $translateFrom,
        string $marketCode,
    ): ?string {
        if ($dimension->type === 'terms') {
            $slugs = array_values(array_map('strval', (array) $raw));

            if ($translateFrom !== null && $translateFrom !== $locale) {
                $translated = [];

                foreach ($slugs as $slug) {
                    $key = $dimension->keyForSlug($slug, $translateFrom, $marketCode);

                    if ($key === null) {
                        return null;
                    }

                    $target = $dimension->slugForKey($key, $locale, $marketCode);

                    if ($target === null) {
                        return null;
                    }

                    $translated[] = $target;
                }

                $slugs = $translated;
            }

            sort($slugs);

            return implode('+', $slugs);
        }

        if ($raw === null || is_array($raw) || is_bool($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    /**
     * Localized, human-readable range (`entre-100-200-eur`) and date (`desde-X`).
     */
    private function shape(FilterDimension $dimension, string $payload, string $locale, ?Market $market): string
    {
        $connectors = $this->connectors($locale);

        if ($dimension->type === 'date') {
            $connector = $this->ownsUpperBound($dimension) ? ($connectors['to'] ?? '') : ($connectors['from'] ?? '');

            return $connector !== '' ? $connector.'-'.$payload : $payload;
        }

        $unit = $dimension->unitFor($locale, $market?->currency);
        $suffix = $unit !== '' ? '-'.$unit : '';

        [$min, $max] = array_pad(explode('-', $payload, 2), 2, '');
        $min = trim($min);
        $max = trim($max);

        if ($min !== '' && $max !== '') {
            $connector = $connectors['between'] ?? '';

            return ($connector !== '' ? $connector.'-' : '').$min.'-'.$max.$suffix;
        }

        if ($min !== '') {
            $connector = $connectors['from'] ?? '';

            return ($connector !== '' ? $connector.'-' : '').$min.$suffix;
        }

        $connector = $connectors['to'] ?? '';

        return ($connector !== '' ? $connector.'-' : '').$max.$suffix;
    }

    /**
     * @return array<string, string>
     */
    private function connectors(string $locale): array
    {
        $configured = (array) config('front_filters.connectors', []);
        $connectors = [];

        foreach ($configured as $name => $translations) {
            $candidate = $translations[$locale] ?? null;

            if (! is_string($candidate) || $candidate === '') {
                $fallback = reset($translations);
                $candidate = is_string($fallback) ? $fallback : null;
            }

            if ($candidate !== null && $candidate !== '') {
                $connectors[(string) $name] = $candidate;
            }
        }

        return $connectors;
    }

    private function ownsUpperBound(FilterDimension $dimension): bool
    {
        return $dimension->bound === 'to';
    }

    /**
     * Market of the filter set (kept deterministic so the currency suffix of a
     * range is identical in web, CLI and tests).
     */
    private function market(FilterSet $set): ?Market
    {
        try {
            return Market::query()->where('code', $set->market)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<int, FilterDimension>
     */
    private function ordered(array $dimensions): array
    {
        usort($dimensions, static function (FilterDimension $a, FilterDimension $b): int {
            if ($a->implicit !== $b->implicit) {
                return $a->implicit ? -1 : 1;
            }

            return $a->order <=> $b->order;
        });

        return $dimensions;
    }
}

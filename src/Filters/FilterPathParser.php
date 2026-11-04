<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

/**
 * Parses a friendly (markerless) filter path into a {@see FilterSet}.
 *
 * Resolution order for a bare segment — deterministic and declared, never a
 * guess (see docs/20):
 *
 *   2. inline qualifiers            `etiqueta-laravel`, `3-habitaciones`
 *   3. reserved tokens              enum options / bool markers (`recientes`)
 *   4. localized connectors+shapes  dates (`desde-2026-01-01`) and ranges (`entre-100-200-eur`)
 *   5. term vocabularies            exact slugs, `+` joins values (`laravel+vue`)
 *   6. search fallback              the last segment, only when the module declares it
 *
 * Unknown or genuinely ambiguous payloads resolve to `null`, which the caller
 * must translate into a 404 — never a partial or ambiguous result.
 */
final class FilterPathParser
{
    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @param  array<string, mixed>  $values
     */
    public function parse(
        string $module,
        string $market,
        string $locale,
        string $path,
        array $dimensions,
        array $values = [],
    ): ?FilterSet {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        if (strlen($path) > (int) config('front_filters.limits.max_path_length', 80)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $maxDimensions = (int) config('front_filters.limits.max_dimensions', 3);
        $maxValues = (int) config('front_filters.limits.max_values_per_dimension', 3);

        // The implicit dimension may consume several leading segments when its
        // vocabulary is hierarchical (`tecnologia/ia-geo/modelos-llm`).
        $segments = $this->consumeImplicitPrefix($dimensions, $locale, $segments, $values, $market);

        $count = count($segments);

        for ($i = 0; $i < $count; $i++) {
            $segment = $segments[$i];
            $isLast = $i === $count - 1;

            $resolved = $this->resolveBareSegment($segment, $dimensions, $locale, $values, $isLast, $market);

            if ($resolved === null) {
                return null;
            }
        }

        if (count($values) > $maxDimensions) {
            return null;
        }

        foreach ($values as $value) {
            if (is_array($value) && count($value) > $maxValues) {
                return null;
            }
        }

        return new FilterSet($module, $market, $locale, $values);
    }

    /**
     * Resolve one bare segment (no explicit marker) through the declared order.
     *
     * @param  array<int, FilterDimension>  $dimensions
     * @param  array<string, mixed>  $values
     */
    private function resolveBareSegment(
        string $segment,
        array $dimensions,
        string $locale,
        array &$values,
        bool $isLast,
        string $marketCode,
    ): ?bool {
        // Reserved tokens (enum options / bool markers) — they always win.
        foreach ($dimensions as $dimension) {
            if ($dimension->type !== 'enum' && $dimension->type !== 'bool') {
                continue;
            }

            if (! in_array($segment, $dimension->reservedTokens($locale), true)) {
                continue;
            }

            if (array_key_exists($dimension->key, $values)) {
                continue;
            }

            $values[$dimension->key] = $dimension->type === 'bool' ? true : $segment;

            return true;
        }

        // Localized shapes (dates / ranges): `desde-2026-01-01`, `entre-100-200-eur`.
        foreach ($dimensions as $dimension) {
            if (! in_array($dimension->type, ['date', 'range'], true)) {
                continue;
            }

            $parsed = $this->parseShape($dimension, $segment, $locale);

            if ($parsed === null) {
                continue;
            }

            // A single segment may carry both date bounds (`desde-X-hasta-Y`).
            if (is_array($parsed)) {
                foreach ($parsed as $key => $value) {
                    if (! array_key_exists($key, $values)) {
                        $values[$key] = $value;
                    }
                }

                return true;
            }

            if (! array_key_exists($dimension->key, $values)) {
                $values[$dimension->key] = $parsed;
            }

            return true;
        }

        // Inline qualifiers (`etiqueta-laravel`, `3-habitaciones`). A qualifier
        // whose payload does not fit simply steps aside.
        foreach ($this->qualifiers($dimensions, $locale) as $qualifier => $dimension) {
            if ($qualifier === '' || ! str_starts_with($segment, $qualifier.'-')) {
                continue;
            }

            $payload = substr($segment, strlen($qualifier) + 1);

            if ($payload !== '' && $this->assign($dimension, $payload, $locale, $values, $marketCode)) {
                return true;
            }
        }

        // Term vocabularies, in the module's declared precedence order.
        foreach ($dimensions as $dimension) {
            if ($dimension->type !== 'terms' || $dimension->implicit) {
                continue;
            }

            $resolved = $this->parseTerms($segment, $dimension, $locale, $marketCode);

            if ($resolved === null) {
                continue;
            }

            if (array_key_exists($dimension->key, $values) && ! $dimension->multi) {
                continue;
            }

            $values[$dimension->key] = $resolved;

            return true;
        }

        // Search fallback (last resort, last segment, opt-in per module).
        if ($isLast) {
            foreach ($dimensions as $dimension) {
                if ($dimension->type !== 'text' || array_key_exists($dimension->key, $values)) {
                    continue;
                }

                if (preg_match('/^[a-z0-9\-]{1,60}$/', $segment) !== 1) {
                    continue;
                }

                $values[$dimension->key] = $segment;

                return true;
            }
        }

        return null;
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $segments
     * @return array<int, string>
     */
    private function consumeImplicitPrefix(array $dimensions, string $locale, array $segments, array &$values, string $marketCode): array
    {
        $implicit = null;

        foreach ($dimensions as $dimension) {
            if ($dimension->implicit && ! array_key_exists($dimension->key, $values)) {
                $implicit = $dimension;

                break;
            }
        }

        if ($implicit === null || $implicit->type !== 'terms' || $segments === []) {
            return $segments;
        }

        $vocabulary = array_keys($implicit->vocabulary($locale, $marketCode));

        for ($take = min(count($segments), 5); $take >= 1; $take--) {
            $candidate = implode('/', array_slice($segments, 0, $take));

            if (in_array($candidate, $vocabulary, true)) {
                $values[$implicit->key] = [$candidate];

                return array_slice($segments, $take);
            }
        }

        return $segments;
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<string, FilterDimension> qualifier => dimension (longest first)
     */
    private function qualifiers(array $dimensions, string $locale): array
    {
        $map = [];

        foreach ($dimensions as $dimension) {
            $qualifier = $dimension->qualifier($locale);

            if ($qualifier !== '') {
                $map[$qualifier] = $dimension;
            }
        }

        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $map;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assign(FilterDimension $dimension, string $payload, string $locale, array &$values, string $marketCode): bool
    {
        $payload = trim($payload, '-');

        if ($payload === '') {
            return false;
        }

        if ($dimension->type === 'bool') {
            $values[$dimension->key] = true;

            return true;
        }

        if ($dimension->type === 'terms') {
            $resolved = $this->parseTerms($payload, $dimension, $locale, $marketCode);

            if ($resolved === null) {
                return false;
            }

            $values[$dimension->key] = $resolved;

            return true;
        }

        if ($dimension->type === 'enum') {
            if (! array_key_exists($payload, $dimension->optionLabels($locale))) {
                return false;
            }

            $values[$dimension->key] = $payload;

            return true;
        }

        if ($dimension->type === 'text') {
            $values[$dimension->key] = $payload;

            return true;
        }

        $parsed = $this->parseShape($dimension, $payload, $locale);

        if ($parsed === null || is_array($parsed)) {
            return false;
        }

        $values[$dimension->key] = $parsed;

        return true;
    }

    /**
     * Parse a date or range payload through its localized connectors.
     *
     * @return array<string, string>|string|null a string for one dimension, a map for both bounds
     */
    private function parseShape(FilterDimension $dimension, string $payload, string $locale): array|string|null
    {
        $connectors = $this->connectors($locale);
        $unit = $dimension->unitFor($locale);

        if ($dimension->type === 'date') {
            return $this->parseDateShape($dimension, $payload, $connectors);
        }

        $alternation = implode('|', array_map(
            static fn (string $connector): string => preg_quote($connector, '/'),
            array_unique(array_values($connectors)),
        ));

        $unitSuffix = $unit !== '' ? '(?:-'.preg_quote($unit, '/').')?' : '';

        // Both bounds: `entre-100-200-eur`.
        $both = '/^('.$alternation.')-(\d{0,9})-(\d{0,9})'.$unitSuffix.'$/u';

        if (preg_match($both, $payload, $matches) === 1 && $matches[2].$matches[3] !== '') {
            return $matches[2].'-'.$matches[3];
        }

        // One bound: `desde-100-eur` / `hasta-200-eur`.
        $single = '/^('.$alternation.')-(\d{1,9})'.$unitSuffix.'$/u';

        if (preg_match($single, $payload, $matches) === 1) {
            return $this->isUpperBound($matches[1], $connectors) ? '-'.$matches[2] : $matches[2].'-';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $connectors
     * @return array{date_from: string, date_to: string}|string|null
     */
    private function parseDateShape(FilterDimension $dimension, string $payload, array $connectors): array|string|null
    {
        $datePattern = '\d{4}-\d{2}-\d{2}';

        foreach ($connectors as $name => $connector) {
            // The dimension owns its bound: `date_from` uses "from", `date_to` "to".
            if (! $this->connectorOwns($dimension, $name)) {
                continue;
            }

            if (preg_match('/^'.preg_quote($connector, '/').'-('.$datePattern.')$/', $payload, $m) === 1 && $this->isValidDate($m[1])) {
                return $m[1];
            }
        }

        // Both bounds in one segment: `desde-2026-01-01-hasta-2026-06-30`.
        if (preg_match('/^(.+?)-('.$datePattern.')-(.+?)-('.$datePattern.')$/', $payload, $m) === 1) {
            $from = $this->isValidDate($m[2]) ? $m[2] : null;
            $to = $this->isValidDate($m[4]) ? $m[4] : null;

            if ($from === null || $to === null) {
                return null;
            }

            return ['date_from' => $from, 'date_to' => $to];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $connectors
     */
    private function isUpperBound(string $connector, array $connectors): bool
    {
        return in_array($connector, [$connectors['to'] ?? '', $connectors['under'] ?? ''], true);
    }

    private function connectorOwns(FilterDimension $dimension, string $connectorName): bool
    {
        if ($dimension->bound === 'to') {
            return in_array($connectorName, ['to', 'under'], true);
        }

        if ($dimension->bound === 'from') {
            return in_array($connectorName, ['from', 'over'], true);
        }

        return in_array($connectorName, ['from', 'to'], true);
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

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * @return array<int, string>|null
     */
    private function parseTerms(string $payload, FilterDimension $dimension, string $locale, string $marketCode): ?array
    {
        $vocabulary = array_keys($dimension->vocabulary($locale, $marketCode));

        if ($vocabulary === []) {
            return null;
        }

        $parts = explode('+', $payload);

        if (count($parts) > 1) {
            foreach ($parts as $part) {
                if (! in_array($part, $vocabulary, true)) {
                    return null;
                }
            }

            return $parts;
        }

        return in_array($payload, $vocabulary, true) ? [$payload] : null;
    }
}

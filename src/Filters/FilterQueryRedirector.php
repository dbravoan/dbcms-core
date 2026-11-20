<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Models\Market;
use Illuminate\Support\Str;

/**
 * Turns a filter **form** submission into the canonical friendly URL.
 *
 * Rich controls (ranges, date pickers, multi-selects, search boxes) cannot be
 * expressed as a single link, so they submit a normal GET form with dimension
 * keys; this service maps those keys to a {@see FilterSet} and returns the
 * canonical path, which the caller 301s to. One implementation serves every
 * module, so the browser never keeps a query string and no duplicate URL is
 * ever served.
 *
 * Accepted keys per dimension:
 *   terms   `?tag[]=laravel&tag[]=vue`        (or `?tag=laravel+vue`)
 *   enum    `?sort=recientes`
 *   bool    `?featured=1`
 *   date    `?date_from=2026-01-01`
 *   range   `?price_min=100&price_max=200`    (or `?price=100-200`)
 *   text    `?search=mi termino`
 */
final class FilterQueryRedirector
{
    public function __construct(
        private readonly FilterUrlBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function canonicalPath(FilterableModule $module, Market $market, string $locale, array $query): ?string
    {
        $values = [];

        foreach ($module->filterDimensions() as $dimension) {
            $key = $dimension->key;

            if ($dimension->type === 'range') {
                $range = $this->range($query, $key);

                if ($range !== null) {
                    $values[$key] = $range;
                }

                continue;
            }

            if (! array_key_exists($key, $query)) {
                continue;
            }

            $raw = $query[$key];

            if ($dimension->type === 'bool') {
                if (filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
                    $values[$key] = true;
                }

                continue;
            }

            if ($dimension->type === 'enum') {
                $option = (string) $raw;

                if (array_key_exists($option, $dimension->optionLabels($locale))) {
                    $values[$key] = $option;
                }

                continue;
            }

            if ($dimension->type === 'date') {
                $date = $this->date((string) $raw);

                if ($date !== null) {
                    $values[$key] = $date;
                }

                continue;
            }

            if ($dimension->type === 'text') {
                $term = Str::slug((string) $raw);

                if ($term !== '') {
                    $values[$key] = $term;
                }

                continue;
            }

            // Terms: an array from a multi-select, or a joined string.
            $raw = is_array($raw) ? $raw : preg_split('/[+_]/', (string) $raw);

            $slugs = array_values(array_filter(array_map(
                static fn (mixed $slug): string => trim((string) $slug),
                is_array($raw) ? $raw : [],
            )));

            $max = (int) config('front_filters.limits.max_values_per_dimension', 3);
            $slugs = array_slice(array_values(array_unique($slugs)), 0, $max);

            if ($slugs !== []) {
                $values[$key] = $slugs;
            }
        }

        if ($values === []) {
            return null;
        }

        $set = new FilterSet($module->filterModuleKey(), (string) $market->code, $locale, $values);

        return $this->builder->canonicalPath($set, $module->filterDimensions(), $locale, $market);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function range(array $query, string $key): ?string
    {
        $min = $this->digits($query[$key.'_min'] ?? null);
        $max = $this->digits($query[$key.'_max'] ?? null);

        if ($min === '' && $max === '') {
            $compact = (string) ($query[$key] ?? '');

            return preg_match('/^\d{0,9}-\d{0,9}$/', $compact) === 1 ? $compact : null;
        }

        return $min.'-'.$max;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private function date(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return ($date !== false && $date->format('Y-m-d') === trim($value)) ? trim($value) : null;
    }
}

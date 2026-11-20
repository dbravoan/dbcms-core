<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

/**
 * Immutable, fully-resolved filter request for one front-office page.
 *
 * Built by {@see FilterPathParser} from a friendly URL and consumed by the
 * module front controller (never from `request('q')` / `request('page')`).
 */
final class FilterSet
{
    /**
     * @param  array<string, mixed>  $values  dimension key => parsed value
     */
    public function __construct(
        public readonly string $module,
        public readonly string $market,
        public readonly string $locale,
        public readonly array $values = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * @return array<int, string>
     */
    public function list(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? array_values($value) : [];
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key): ?int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Number of active filtering dimensions (excluding results pagination).
     */
    public function dimensionCount(): int
    {
        return count($this->values);
    }

    /**
     * Stable, locale-independent signature used for curated SEO lookups and
     * cache keys (e.g. `category=camisetas|tag=laravel|sort=recientes`).
     */
    public function signature(): string
    {
        $normalized = [];

        foreach ($this->values as $key => $value) {
            if (is_array($value)) {
                $value = array_values(array_map('strval', $value));
                sort($value);
                $normalized[$key] = implode(',', $value);

                continue;
            }

            $normalized[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        ksort($normalized);

        $parts = [];
        foreach ($normalized as $key => $value) {
            if ($value !== '') {
                $parts[] = $key.'='.$value;
            }
        }

        return implode('|', $parts);
    }
}

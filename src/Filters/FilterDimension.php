<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use Closure;

/**
 * Declares one addressable filter dimension of a front-office module.
 *
 * A dimension knows how it is spelled in the URL (localized marker), how its
 * value payload is parsed (`terms`, `range`, `date`, `bool`, `enum`) and how
 * its values are labelled in the composed SEO text.
 */
final class FilterDimension
{
    /**
     * @param  array<string, string>  $markers  locale => marker (defaults to config)
     * @param  array<string, array<string, string>>  $options  option => [locale => label] (enum)
     * @param  Closure(string, string): array<string, array{label: string, key: string|int}>|null  $vocabulary
     * @param  array<string, string>  $units  locale => unit label (range)
     * @param  list<string>  $reserved
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type = 'terms',
        public readonly bool $multi = false,
        public readonly int $order = 100,
        public readonly bool $indexable = true,
        public readonly array $markers = [],
        public readonly array $options = [],
        public readonly ?Closure $vocabulary = null,
        public readonly array $units = [],
        public readonly bool $implicit = false,
        public readonly bool $bare = true,
        public readonly ?string $qualifier = null,
        public readonly bool $marketCurrency = false,
        public readonly array $reserved = [],
        public readonly ?string $bound = null,
        public readonly ?string $control = null,
    ) {}

    public static function terms(string $key, bool $multi = false, int $order = 100, ?Closure $vocabulary = null, bool $indexable = true, bool $implicit = false): self
    {
        return new self($key, 'terms', $multi, $order, $indexable, vocabulary: $vocabulary, implicit: $implicit);
    }

    /** @param array<string, string> $units */
    public static function range(string $key, int $order = 100, array $units = [], bool $marketCurrency = false): self
    {
        return new self($key, 'range', false, $order, true, units: $units, marketCurrency: $marketCurrency);
    }

    public static function date(string $key, int $order = 100, ?string $bound = null): self
    {
        return new self($key, 'date', false, $order, bound: $bound);
    }

    public static function text(string $key, int $order = 100): self
    {
        return new self($key, 'text', false, $order);
    }

    public static function bool(string $key, int $order = 100): self
    {
        return new self($key, 'bool', false, $order);
    }

    /**
     * @param  array<string, array<string, string>>  $options
     */
    public static function enum(string $key, array $options, int $order = 100): self
    {
        return new self($key, 'enum', false, $order, true, options: $options);
    }

    public function marker(string $locale): string
    {
        return (string) ($this->markers[$locale]
            ?? (config("front_filters.markers.{$this->key}.{$locale}") ?? $this->key));
    }

    public function unit(string $locale): string
    {
        return (string) ($this->units[$locale]
            ?? (config("front_filters.units.{$this->key}.{$locale}") ?? ''));
    }

    /**
     * Localized slug => label map for a `terms` dimension.
     *
     * @return array<string, string>
     */
    public function labels(string $locale, string $marketCode): array
    {
        $labels = [];

        foreach ($this->vocabulary($locale, $marketCode) as $slug => $entry) {
            $labels[$slug] = $entry['label'];
        }

        return $labels;
    }

    /**
     * Rich vocabulary entry for a `terms` dimension: slug => {label, key}.
     *
     * @return array<string, array{label: string, key: string|int}>
     */
    public function vocabulary(string $locale, string $marketCode): array
    {
        if ($this->vocabulary === null) {
            return [];
        }

        return ($this->vocabulary)($locale, $marketCode);
    }

    /**
     * Resolve the slug of a shared value key in another locale (hreflang).
     */
    public function slugForKey(string|int $key, string $locale, string $marketCode): ?string
    {
        foreach ($this->vocabulary($locale, $marketCode) as $slug => $entry) {
            if ((string) $entry['key'] === (string) $key) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Resolve the shared value key for a slug in a given locale (hreflang).
     */
    public function keyForSlug(string $slug, string $locale, string $marketCode): string|int|null
    {
        return $this->vocabulary($locale, $marketCode)[$slug]['key'] ?? null;
    }

    /**
     * Localized option slug => label map for an `enum` dimension.
     *
     * @return array<string, string>
     */
    public function optionLabels(string $locale): array
    {
        $labels = [];

        foreach ($this->options as $option => $translations) {
            $labels[$option] = (string) ($translations[$locale] ?? reset($translations) ?: $option);
        }

        return $labels;
    }

    /**
     * Inline qualifier for this dimension: the marker used when a bare value
     * would be ambiguous (`etiqueta-laravel`, `3-habitaciones`).
     */
    public function qualifier(string $locale): string
    {
        return $this->qualifier ?? $this->marker($locale);
    }

    /**
     * Front-office control used to pick this dimension. Declared explicitly by
     * the module or derived from the dimension type:
     * terms multi → checkboxes · terms/enum single → radios/select · bool →
     * switch · range → min/max inputs · date → date picker · text → search box.
     */
    public function control(): string
    {
        if ($this->control !== null && $this->control !== '') {
            return $this->control;
        }

        return match ($this->type) {
            'range' => 'range',
            'date' => 'date',
            'bool' => 'switch',
            'text' => 'search',
            'enum' => 'select',
            default => $this->multi ? 'checkboxes' : 'radios',
        };
    }

    /**
     * Tokens this dimension owns exclusively, so a bare segment is resolved
     * deterministically: enum option slugs and the bool marker are always
     * reserved, plus any explicitly declared token.
     *
     * @return array<int, string>
     */
    public function reservedTokens(string $locale): array
    {
        $tokens = $this->reserved;

        if ($this->type === 'enum') {
            $tokens = array_merge($tokens, array_keys($this->optionLabels($locale)));
        }

        if ($this->type === 'bool') {
            $tokens[] = $this->marker($locale);
        }

        return array_values(array_unique(array_filter($tokens)));
    }

    /**
     * Localized range unit, falling back to the market currency.
     */
    public function unitFor(string $locale, ?string $marketCurrency = null): string
    {
        $unit = $this->unit($locale);

        if ($unit !== '') {
            return $unit;
        }

        return $this->marketCurrency ? strtolower((string) $marketCurrency) : '';
    }
}

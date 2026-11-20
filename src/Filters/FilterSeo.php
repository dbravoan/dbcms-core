<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

/**
 * Resolved SEO surface of a filter/search page (never a static file: composed
 * or curated at request time, per market + locale).
 */
final class FilterSeo
{
    public function __construct(
        public readonly string $title,
        public readonly string $heading,
        public readonly string $description,
        public readonly string $robots,
        public readonly bool $indexable,
        public readonly bool $curated,
    ) {}
}

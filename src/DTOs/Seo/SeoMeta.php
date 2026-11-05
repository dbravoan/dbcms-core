<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Seo;

use DbCms\Core\DTOs\BaseDTO;

/**
 * Complete SEO/GEO metadata for a single page.
 *
 * Every front-office theme renders this through the shared `themes::components.seo-head`
 * partial, so canonical URLs, OpenGraph, Twitter cards, hreflang, robots and the
 * Schema.org JSON-LD graph are always emitted consistently.
 */
readonly class SeoMeta extends BaseDTO
{
    /**
     * @param  array<int, array{hreflang: string, href: string}>  $alternates
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $type = 'website',
        public string $locale = 'es',
        public ?string $siteName = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public string $robots = 'index, follow, max-image-preview:large, max-snippet:-1',
        public array $alternates = [],
        public array $schema = [],
    ) {}
}

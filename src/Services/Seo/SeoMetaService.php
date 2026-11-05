<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use Carbon\CarbonInterface;
use DbCms\Core\DTOs\Seo\SeoMeta;

/**
 * Builds complete SEO/GEO metadata for front-office pages.
 *
 * Every theme renders the result through `themes::components.seo-head`, so
 * canonical/OG/Twitter/hreflang/robots/JSON-LD are emitted identically across
 * themes and modules.
 */
final class SeoMetaService
{
    public function __construct(
        private readonly JsonLdSchemaService $schema
    ) {}

    /**
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     * @param  array<string,mixed>|null  $entitySchema
     * @param  list<array{hreflang:string,href:string}>|null  $alternates
     * @param  array<string,mixed>|null  $schemaOverride
     */
    public function forWebPage(
        string $title,
        string $description,
        ?string $canonical = null,
        array $breadcrumbs = [],
        ?string $image = null,
        ?string $imageAlt = null,
        ?array $entitySchema = null,
        string $type = 'website',
        ?string $robots = null,
        ?array $alternates = null,
        ?array $schemaOverride = null,
        ?CarbonInterface $modifiedAt = null,
        ?string $locale = null,
    ): SeoMeta {
        $url = $canonical ?? url()->current();
        $locale = str_replace('_', '-', $locale ?? app()->getLocale());

        if ($schemaOverride !== null) {
            $schema = $schemaOverride;
        } elseif ($entitySchema !== null) {
            $schema = $this->schema->generateEntityGraph($entitySchema, $url, $locale, $breadcrumbs);
        } else {
            $schema = $this->schema->generateWebPageGraph($title, $description, $url, $locale, $breadcrumbs);
        }

        if ($modifiedAt !== null) {
            if (isset($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as &$node) {
                    if (is_array($node) && ($node['@type'] ?? null) === 'WebPage') {
                        $node['dateModified'] = $modifiedAt->toIso8601String();
                    }
                }
                unset($node);
            } else {
                $schema['dateModified'] = $modifiedAt->toIso8601String();
            }
        }

        return new SeoMeta(
            title: $this->trim($title, 120),
            description: $this->trim($description, 300),
            canonical: $url,
            type: $type,
            locale: $locale,
            siteName: $this->siteName(),
            image: $image,
            imageAlt: $imageAlt ?? $title,
            robots: $robots ?? $this->robots(),
            alternates: $alternates ?? $this->alternates(),
            schema: $schema,
        );
    }

    /**
     * Resolves the site name (settings → config), never a hardcoded brand.
     */
    private function siteName(): string
    {
        if (function_exists('setting')) {
            $name = (string) setting('general.site_name', '');
            if ($name !== '') {
                return $name;
            }
        }

        return (string) config('cms.name', '');
    }

    /**
     * Robots directive. Non-production or previewed pages are noindexed.
     */
    private function robots(): string
    {
        if (! app()->isProduction() || request()->boolean('preview')) {
            return 'noindex, nofollow';
        }

        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }

    /**
     * hreflang alternates emitted by the view composer (if any).
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function alternates(): array
    {
        $raw = view()->shared('hreflangs', []);

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $hreflang => $href) {
            if (is_string($hreflang) && is_string($href)) {
                $out[] = ['hreflang' => $hreflang, 'href' => $href];
            }
        }

        return $out;
    }

    private function trim(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_strlen($value) > $limit ? rtrim(mb_substr($value, 0, $limit - 1)).'…' : $value;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Contracts\Services\SeoServiceInterface;
use DbCms\Core\Filters\FilterAlternateUrls;
use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Market;

class SitemapService implements SeoServiceInterface
{
    /**
     * Helper to safely escape XML URLs.
     */
    protected function xmlUrl(string $url): string
    {
        return htmlspecialchars($url, ENT_XML1, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function generateSitemapXml(?Market $market = null, ?string $locale = null, ?int $part = null): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        $xml .= ($market !== null && ! empty($locale))
            ? $this->buildScopedSitemap($market, $locale)
            : $this->buildGlobalSitemap();

        $xml .= '</urlset>';

        preg_match_all('/<url>.*?<\/url>\s*/s', $xml, $matches);
        $documents = app(SitemapDocumentPartitioner::class)->partition(
            $matches[0],
            (int) config('seo.sitemap.max_urls', SitemapDocumentPartitioner::MAX_URLS),
            (int) config('seo.sitemap.max_bytes', SitemapDocumentPartitioner::MAX_BYTES)
        );
        if ($part !== null) {
            return $documents[$part - 1] ?? '';
        }
        if (count($documents) === 1) {
            return $documents[0];
        }
        $base = $market !== null && $locale !== null
            ? app(LocalizationServiceInterface::class)->url('/', $market, $locale)
            : rtrim((string) config('app.url'), '/');
        $index = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_keys($documents) as $number) {
            $index .= '<sitemap><loc>'.$this->xmlUrl($base.'/sitemap-part-'.($number + 1).'.xml').'</loc></sitemap>';
        }

        return $index.'</sitemapindex>';
    }

    /**
     * Regional sitemap scoped to a single market + locale (e.g. /es/es/sitemap.xml).
     */
    private function buildScopedSitemap(Market $market, string $locale): string
    {
        if (! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return '';
        }
        $discovery = app(PublicContentDiscoveryInterface::class);
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $entries = [$base => $this->urlEntry($base, null, 'daily', '1.0')];
        foreach ($discovery->schemas() as $schema) {
            $definition = $schema->publicContent;
            if ($definition === null) {
                continue;
            }
            if ($definition->includeHub) {
                $hub = $discovery->url($schema->type, $market, $locale, '');
                $entries[$hub] = $this->urlEntry($hub, null, $definition->changefreq, $definition->priority);
            }
            foreach ($discovery->query($schema->type, $market, $locale)->get() as $entity) {
                $location = $discovery->entityUrl($schema->type, $entity, $market, $locale);
                $alternates = [];
                foreach ($market->languages()->where('is_active', true)->get() as $language) {
                    if ($discovery->query($schema->type, $market, $language->code)->whereKey($entity->getKey())->exists()) {
                        $alternates[strtolower($language->code).'-'.strtoupper($market->code)] = $discovery->entityUrl($schema->type, $entity, $market, $language->code);
                    }
                }
                $entries[$location] = $this->urlEntry($location, app(PublicContentModifiedService::class)->forEntity($entity)?->toIso8601String(), $definition->changefreq, $definition->priority, $alternates);
            }
        }

        foreach (app(PublicSemanticRegistry::class)->urls($market, $locale) as $url => $label) {
            $entries[$url] = $this->urlEntry($url);
        }

        return implode('', $entries).$this->scopedFilterPages($market, $locale, $base);
    }

    private function buildGlobalSitemap(): string
    {
        $xml = '';
        foreach (app(PublicContentDiscoveryInterface::class)->markets() as $market) {
            foreach ($market->languages as $language) {
                $xml .= $this->buildScopedSitemap($market, $language->code);
            }
        }

        return $xml;
    }

    private function scopedFilterPages(Market $market, string $locale, string $base): string
    {
        try {
            $rows = FilterPage::query()
                ->where('is_indexable', true)
                ->where('locale', $locale)
                ->where(function ($query) use ($market): void {
                    $query->where('market_code', $market->code)->orWhereNull('market_code');
                })
                ->get();

            if ($rows->isEmpty()) {
                return '';
            }

            $resolver = app(FilterPathResolver::class);
            $localization = app(LocalizationServiceInterface::class);
            $alternates = app(FilterAlternateUrls::class);

            $xml = '';

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, $locale, (string) ($row->path ?? ''));

                // A curated landing that does not pass the quality gate stays
                // noindex at runtime, so it must not be published here either.
                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, $locale)) {
                    continue;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, $locale);
                $lastMod = $row->updated_at?->toIso8601String();

                $hreflangs = [];

                foreach ($alternates->build(
                    $resolved['set'],
                    $resolved['module']->filterDimensions(),
                    $resolved['module'],
                    $market,
                    $localization
                ) as $alternate) {
                    $hreflangs[$alternate['hreflang']] = $alternate['href'];
                }

                $xml .= $this->urlEntry(
                    "{$base}/{$prefix}/{$resolved['canonical']}",
                    $lastMod,
                    'weekly',
                    '0.6',
                    $hreflangs
                );
            }

            return $xml;
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array<string, string> $alternates */
    private function urlEntry(string $loc, ?string $lastMod = null, string $changefreq = 'weekly', string $priority = '0.8', array $alternates = []): string
    {
        $entry = "  <url>\n";
        $entry .= '    <loc>'.$this->xmlUrl($loc)."</loc>\n";
        if ($lastMod !== null) {
            $entry .= "    <lastmod>{$lastMod}</lastmod>\n";
        }
        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";

        foreach ($alternates as $hreflang => $href) {
            $entry .= '    <xhtml:link rel="alternate" hreflang="'.$hreflang.'" href="'.$this->xmlUrl($href)."\" />\n";
        }

        $entry .= "  </url>\n";

        return $entry;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use Carbon\CarbonInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Segmented sitemap index: /sitemap.xml lists one child sitemap per content type
 * (pages, posts, products, galleries), each generated from its own query so search
 * engines can fetch only what changed.
 *
 * Results are cached with the page-cache version, so a content edit regenerates
 * them on the next crawl without doing per-request work.
 */
final class SitemapIndexService
{
    public function __construct(
        private readonly PageCacheService $pageCache
    ) {}

    /**
     * Sitemap index document.
     */
    public function indexXml(): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->documentNames() as $segment) {
            $xml .= '  <sitemap>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($base.'/sitemaps/'.$segment.'.xml', ENT_XML1).'</loc>'."\n";
            $xml .= '  </sitemap>'."\n";
        }

        return $xml.'</sitemapindex>';
    }

    /**
     * A single segment document, or null when the segment is unknown.
     */
    public function segmentXml(string $segment): ?string
    {
        if (! preg_match('/^([a-z]+)(?:-([1-9][0-9]*))?$/D', $segment, $match) || ! array_key_exists($match[1], $this->segments())) {
            return null;
        }
        $documents = $this->documents($match[1]);

        return $documents[(int) ($match[2] ?? 1) - 1] ?? null;
    }

    /** @return list<string> */
    private function documentNames(): array
    {
        $names = [];
        foreach (array_keys($this->segments()) as $segment) {
            $documents = $this->documents($segment);
            foreach (array_keys($documents) as $index) {
                $names[] = count($documents) === 1 ? $segment : $segment.'-'.($index + 1);
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function documents(string $segment): array
    {
        $entries = [];
        foreach ($this->segmentEntries($segment) ?? [] as $entry) {
            $xml = '<url><loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';
            if ($entry['lastmod'] !== null) {
                $xml .= '<lastmod>'.htmlspecialchars($entry['lastmod'], ENT_XML1).'</lastmod>';
            }
            $entries[] = $xml.'<changefreq>'.$entry['changefreq'].'</changefreq><priority>'.$entry['priority'].'</priority></url>'."\n";
        }

        return app(SitemapDocumentPartitioner::class)->partition(
            $entries,
            (int) config('seo.sitemap.max_urls', SitemapDocumentPartitioner::MAX_URLS),
            (int) config('seo.sitemap.max_bytes', SitemapDocumentPartitioner::MAX_BYTES)
        );
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>|null
     */
    public function segmentEntries(string $segment): ?array
    {
        if (! isset($this->segments()[$segment])) {
            return null;
        }
        try {
            return Cache::remember(
                sprintf('cms:sitemap:%s:%s', $this->pageCache->visibilityVersion(), $segment),
                $this->pageCache->ttl(),
                function () use ($segment): array {
                    return $this->build($segment);
                }
            );
        } catch (\Throwable) {
            return $this->build($segment);
        }
    }

    /**
     * @return array<string, true>
     */
    private function segments(): array
    {
        $segments = [];
        foreach (app(PublicContentDiscoveryInterface::class)->schemas() as $schema) {
            if ($schema->publicContent === null) {
                continue;
            }
            $segments[$schema->publicContent->segment] = true;
        }

        // Only advertise the curated filter landings segment when it actually
        // has content: an empty child sitemap is a crawl-quality smell.
        if ($this->hasCuratedFilters()) {
            $segments['filters'] = true;
        }

        return $segments;
    }

    private function hasCuratedFilters(): bool
    {
        try {
            return $this->filters() !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>
     */
    private function build(string $segment): array
    {
        if ($segment === 'filters') {
            return $this->filters();
        }
        $entries = [];
        foreach (app(PublicContentDiscoveryInterface::class)->schemas() as $schema) {
            if ($schema->publicContent === null) {
                continue;
            }
            if ($schema->publicContent->segment === $segment) {
                array_push($entries, ...$this->publicEntries($schema->type));
            }
        }

        return $entries;
    }

    /** @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}> */
    private function publicEntries(string $type): array
    {
        $discovery = app(PublicContentDiscoveryInterface::class);
        $entries = [];
        foreach ($discovery->markets() as $market) {
            foreach ($market->languages as $language) {
                foreach ($discovery->query($type, $market, $language->code)->get() as $entity) {
                    $translation = $entity->getRelation('translations')->firstWhere('locale', $language->code);
                    if (! $translation instanceof Model) {
                        continue;
                    }
                    $location = $discovery->entityUrl($type, $entity, $market, $language->code);
                    $updated = app(PublicContentModifiedService::class)->forEntity($entity);
                    $entries[$location] = ['loc' => $location, 'lastmod' => $updated instanceof CarbonInterface ? $updated->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.7'];
                }
            }
        }

        return array_values($entries);
    }

    /**
     * Curated filter landings (only those passing the runtime quality gate).
     *
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>
     */
    private function filters(): array
    {
        $resolver = app(FilterPathResolver::class);
        $localization = app(LocalizationServiceInterface::class);

        return FilterPage::query()
            ->where('is_indexable', true)
            ->orderBy('module')
            ->get()
            ->map(function (FilterPage $row) use ($resolver, $localization): ?array {
                $market = $row->market_code !== null && $row->market_code !== ''
                    ? Market::query()->where('code', $row->market_code)->first()
                    : Market::query()->where('is_default', true)->first();

                if ($market === null || ! $market->is_active || ! $market->languages()->where('code', (string) $row->locale)->where('is_active', true)->exists()) {
                    return null;
                }

                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, (string) $row->locale, (string) ($row->path ?? ''));

                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, (string) $row->locale)) {
                    return null;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, (string) $row->locale);

                return [
                    'loc' => $this->url((string) $market->code, (string) $row->locale, "{$prefix}/{$resolved['canonical']}"),
                    'lastmod' => $row->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function url(?string $market, string $locale, string $path): string
    {
        $model = Market::where('code', $market ?: (string) config('cms.default_market', 'es'))->firstOrFail();

        return app(LocalizationServiceInterface::class)->url($path, $model, $locale);
    }
}

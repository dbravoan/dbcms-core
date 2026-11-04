<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Geo;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\Services\GeoServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Support\Facades\Config;

class LlmsTxtGeneratorService implements GeoServiceInterface
{
    /**
     * {@inheritdoc}
     */
    public function generateLlmsTxt(?Market $market = null, ?string $locale = null): string
    {
        $siteName = (string) (setting('general.site_name', '') ?: Config::get('geo.site_name', Config::get('cms.name', '')));
        $siteDesc = (string) (setting('general.site_description', '') ?: Config::get('geo.site_description', ''));

        return ($market !== null && ! empty($locale))
            ? $this->scopedIndex($market, $locale, $siteName, $siteDesc)
            : $this->globalIndex($siteName, $siteDesc);
    }

    /**
     * Market + locale scoped index (/es/es/llms.txt).
     */
    private function scopedIndex(Market $market, string $locale, string $siteName, string $siteDesc): string
    {
        $base = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
        $output = "# {$siteName} - {$market->name} ({$locale})\n\n";
        $output .= "> {$siteDesc}\n\n";
        $output .= "## Overview & Main Hubs\n";
        $output .= "- [Homepage]({$base}): Portal principal para la región [{$market->code}].\n";
        foreach (app(PublicContentDiscoveryInterface::class)->schemas() as $schema) {
            if ($schema->publicContent === null) {
                continue;
            }
            if ($schema->publicContent->includeHub) {
                $hub = app(PublicContentDiscoveryInterface::class)->url($schema->type, $market, $locale, '');
                $output .= "- [{$schema->label}]({$hub})\n";
            }
        }
        foreach (app(PublicSemanticRegistry::class)->urls($market, $locale) as $url => $label) {
            $output .= "- [{$label}]({$url})\n";
        }
        $output .= "- [Full Semantic Corpus]({$base}/llms-full.txt): Base de conocimiento completa para ingesta de LLMs.\n";
        $output .= "- [Sitemap XML]({$base}/sitemap.xml): Mapa del sitio indexable para rastreadores.\n\n";

        $output .= $this->scopedIndexPages($market, $locale, $base);
        $output .= $this->scopedIndexFilterLandings($market, $locale, $base);
        $output .= app(PublicSemanticRegistry::class)->index($market, $locale);

        return $output;
    }

    /**
     * Curated (indexable) filter landings: the navigable entry points into the
     * dynamic filter space, so an AI engine can traverse the catalogue instead
     * of guessing query strings.
     */
    private function scopedIndexFilterLandings(Market $market, string $locale, string $base): string
    {
        try {
            $rows = FilterPage::query()
                ->where('is_indexable', true)
                ->where('locale', $locale)
                ->where(function ($query) use ($market): void {
                    $query->where('market_code', $market->code)->orWhereNull('market_code');
                })
                ->orderBy('module')
                ->get();

            if ($rows->isEmpty()) {
                return '';
            }

            $resolver = app(FilterPathResolver::class);
            $localization = app(LocalizationServiceInterface::class);

            $output = "## Curated Filters & Collections\n";

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((string) $row->module, (string) $market->code, $locale, (string) ($row->path ?? ''));

                // Same quality gate as the runtime (below it the landing is noindex).
                if ($resolved === null || ! $resolver->meetsQualityGate($resolved, $market, $locale)) {
                    continue;
                }

                $prefix = $localization->getModulePrefix((string) $row->module, $locale);
                $label = (string) ($row->heading ?? $row->title ?? $resolved['canonical']);
                $description = (string) ($row->meta_description ?? '');

                $output .= "- [{$label}]({$base}/{$prefix}/{$resolved['canonical']})"
                    .($description !== '' ? ": {$description}" : '')
                    ."\n";
            }

            return $output."\n";
        } catch (\Throwable) {
            return '';
        }
    }

    private function scopedIndexPages(Market $market, string $locale, string $base): string
    {
        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale, ['translations'])
            ->get();

        if ($pages->isEmpty()) {
            return '';
        }

        $output = "## Published Landing Pages\n";
        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = PublicTranslation::resolve($page, $locale, $market);
            if ($trans instanceof PageTranslation && $trans->slug !== 'home') {
                $desc = ! empty($trans->meta_description) ? $trans->meta_description : '';
                $output .= "- [{$trans->title}]({$base}/{$trans->slug}): {$desc}\n";
            }
        }

        return $output."\n";
    }

    /**
     * Global root index (/llms.txt).
     */
    private function globalIndex(string $siteName, string $siteDesc): string
    {
        $appBaseUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');

        $output = "# {$siteName}\n\n";
        $output .= "> {$siteDesc}\n\n";
        $output .= "## Regional Knowledge Bases (Multi-Market)\n";

        $activeMarkets = app(PublicContentDiscoveryInterface::class)->markets();
        foreach ($activeMarkets as $market) {
            foreach ($market->languages as $language) {
                $regionalUrl = app(LocalizationServiceInterface::class)->url('/llms.txt', $market, $language);
                $output .= "- [{$market->name} - {$language->name} ({$market->code}/{$language->code})]({$regionalUrl}): Índice GEO especializado para {$market->code}/{$language->code}.\n";
            }
        }
        $output .= "\n";

        $output .= "## Overview & API Surface\n";
        $output .= "- [Full Knowledge Base]({$appBaseUrl}/llms-full.txt): Comprehensive semantic index for LLM ingestion.\n";
        $output .= "- [Sitemap Index]({$appBaseUrl}/sitemap.xml): Complete search engine sitemap.\n";
        $output .= "- [API Schema]({$appBaseUrl}/api/v1/geo/metadata): Structured JSON-LD metadata graph.\n\n";

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function generateLlmsFullTxt(?Market $market = null, ?string $locale = null): string
    {
        $output = $this->generateLlmsTxt($market, $locale)."\n\n---\n\n# Full Semantic Corpus\n\n";

        $contexts = $market !== null && ! empty($locale)
            ? [[$market, $locale]]
            : app(PublicContentDiscoveryInterface::class)->markets()->flatMap(fn (Market $active): array => $active->languages->map(fn ($language): array => [$active, $language->code])->all())->all();
        foreach ($contexts as [$contextMarket, $contextLocale]) {
            $output .= "## Market {$contextMarket->code} — {$contextLocale}\n\n";
            $output .= $this->scopedCorpusPages($contextMarket, $contextLocale);
            $output .= app(PublicSemanticRegistry::class)->corpus($contextMarket, $contextLocale);
        }

        return $output;
    }

    private function scopedCorpusPages(Market $market, string $locale): string
    {
        $output = '';
        $pages = app(PublicContentDiscoveryInterface::class)->query('page', $market, $locale)->get();

        foreach ($pages as $page) {
            /** @var Page $page */
            $trans = PublicTranslation::resolve($page, $locale, $market);
            if (! $trans instanceof PageTranslation) {
                continue;
            }

            $output .= "## Page: {$trans->title}\n";
            $output .= '**Source**: '.app(PublicContentDiscoveryInterface::class)->url('page', $market, $locale, (string) $trans->slug)."\n\n";
            if (! empty($trans->meta_description)) {
                $output .= "### Meta Description\n{$trans->meta_description}\n\n";
            }

            $output .= app(PublishedSectionTextInterface::class)->text($page, $market, $locale);
            $output .= "---\n\n";
        }

        return $output;
    }
}

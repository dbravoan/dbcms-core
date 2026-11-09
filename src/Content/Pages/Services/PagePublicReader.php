<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Contracts\PagePublicReaderInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Content\PublicTranslation;
use DbCms\Core\Services\Seo\PublicContentModifiedService;
use DbCms\Core\Services\Seo\SeoMetaService;

final class PagePublicReader implements PagePublicReaderInterface
{
    public function __construct(private readonly FrontViewResolver $views, private readonly PageDomainServiceInterface $pages, private readonly SectionRendererService $renderer, private readonly LocalizationServiceInterface $localization) {}

    public function show(PublicContentContext $context): FrontPageData
    {
        $market = $context->market;
        $locale = $context->locale;
        $slug = $context->slug;
        $pageService = $this->pages;
        $renderer = $this->renderer;
        $localization = $this->localization;
        $targetSlug = ! empty($slug) ? $slug : 'home';

        $moduleManager = app(ModuleManager::class);
        $page = $pageService->resolvePageBySlug($targetSlug, $market, $locale);

        if (! $page && empty($slug)) {
            // Fallback try with localized aliases like 'inicio'
            $page = $pageService->resolvePageBySlug('inicio', $market, $locale);
        }

        if (! $page) {
            if (empty($slug)) {
                return FrontPageData::view('welcome-cms', [
                    'activeMarkets' => Market::where('is_active', true)->get(),
                    'installedModules' => $moduleManager->getActiveModules(),
                ]);
            }

            return FrontPageData::missing();
        }

        $translation = PublicTranslation::resolve($page, $locale, $market);
        if (! $translation instanceof PageTranslation) {
            return FrontPageData::missing();
        }

        $bladeView = app(PageViewRegistry::class)->resolve((string) $page->template);
        $renderedSections = [];
        if ($page->usesSectionContent()) {
            $page->load('publishedSections.translations');
            foreach ($page->publishedSections as $section) {
                $renderedSections[] = $renderer->renderSection($section, $market, $locale);
            }
        }

        $hreflangs = [];
        $alternateUrls = [];
        $discovery = app(PublicContentDiscoveryInterface::class);
        foreach ($discovery->markets() as $alternateMarket) {
            foreach ($alternateMarket->languages as $language) {
                if (! $discovery->query('page', $alternateMarket, $language->code)->whereKey($page->id)->exists()) {
                    continue;
                }
                $url = $discovery->entityUrl('page', $page, $alternateMarket, $language->code);
                $hreflangs[strtolower($language->code).'-'.strtoupper($alternateMarket->code)] = $url;
                $alternateUrls[$alternateMarket->code][$language->code] = $url;
            }
        }
        if ($hreflangs !== []) {
            $hreflangs['x-default'] = $hreflangs['es-ES'] ?? reset($hreflangs);
        }

        $breadcrumbs = [
            ['title' => $locale === 'es' ? 'Inicio' : ($locale === 'ca' ? 'Inici' : 'Home'), 'url' => $localization->url('/', $market)],
        ];

        if ($targetSlug !== 'home' && $targetSlug !== 'inicio') {
            $breadcrumbs[] = [
                'title' => $translation->title ?? 'Página',
                'url' => $localization->url("/{$translation->slug}", $market),
            ];
        }

        $viewName = $bladeView ?? $this->views->resolve('pages.show', 'pages::show');
        $layout = $this->layoutFor($page);

        // Unified SEO/GEO metadata (canonical, OG, Twitter, hreflang, JSON-LD).
        /** @var SeoMetaService $seoMetaService */
        $seoMetaService = app(SeoMetaService::class);
        $seoMeta = $seoMetaService->forWebPage(
            title: $translation->meta_title ?: $translation->title,
            description: $translation->meta_description ?: '',
            canonical: $context->currentUrl,
            modifiedAt: app(PublicContentModifiedService::class)->forEntity($page),
            alternates: array_map(static fn (string $tag, string $href): array => ['hreflang' => $tag, 'href' => $href], array_keys($hreflangs), array_values($hreflangs)),
            breadcrumbs: array_map(
                static fn (array $crumb): array => ['name' => $crumb['title'], 'url' => $crumb['url']],
                $breadcrumbs
            ),
        );

        return FrontPageData::view($viewName, [
            'page' => $page,
            'translation' => $translation,
            'renderedSections' => $renderedSections,
            'hreflangs' => $hreflangs,
            'seoMeta' => $seoMeta,
            'alternateUrls' => $alternateUrls,
            'breadcrumbs' => $breadcrumbs,
            'currentMarket' => $market,
            'currentLocale' => $locale,
            'layout' => $layout,
        ]);
    }

    private function layoutFor(Page $page): string
    {
        $requested = trim((string) $page->layout);

        if ($requested !== '') {
            try {
                return $this->views->layout($requested);
            } catch (\DomainException) {
                // A page imported with an unknown layout must still render with
                // the theme's default layout instead of failing the request.
            }
        }

        return $this->views->layout();
    }
}

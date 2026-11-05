<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Contracts\EditorialPreviewHandlerInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;

final class PageEditorialPreview implements EditorialPreviewHandlerInterface
{
    public function __construct(private readonly FrontViewResolver $views, private readonly SectionRendererService $renderer, private readonly PageViewRegistry $registry) {}

    public function render(Model $entity, PublicContentContext $context): FrontPageData
    {
        if (! $entity instanceof Page) {
            throw new \InvalidArgumentException('Page preview requires a Page.');
        }
        $translation = PublicTranslation::resolve($entity, $context->locale, $context->market);
        if (! $translation instanceof PageTranslation) {
            return FrontPageData::missing();
        }
        $sections = [];
        if ($entity->usesSectionContent()) {
            if (! $entity->relationLoaded('sections')) {
                throw new \LogicException('Page preview requires hydrated sections.');
            }
            foreach ($entity->sections->where('is_active', true)->where('status', 'published')->sortBy('order') as $section) {
                $sections[] = $this->renderer->renderSection($section, $context->market, $context->locale);
            }
        }

        return FrontPageData::view($this->registry->resolve((string) $entity->template) ?? $this->views->resolve('pages.show', 'pages::show'), [
            'page' => $entity,
            'translation' => $translation,
            'renderedSections' => $sections,
            'hreflangs' => [],
            'seoMeta' => null,
            'alternateUrls' => [],
            'breadcrumbs' => [],
            'currentMarket' => $context->market,
            'currentLocale' => $context->locale,
            'layout' => $this->views->layout(),
        ]);
    }
}

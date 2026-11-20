<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Content\Contracts\EditorialPreviewHandlerInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;

final class ComponentEditorialPreview implements EditorialPreviewHandlerInterface
{
    public function __construct(private readonly SectionRendererService $sections) {}

    public function render(Model $entity, PublicContentContext $context): FrontPageData
    {
        if (! $entity instanceof BlockComponent) {
            return FrontPageData::missing();
        }
        $translation = PublicTranslation::resolve($entity, $context->locale, $context->market);
        if ($translation === null) {
            return FrontPageData::missing();
        }
        $html = '';
        foreach ($entity->getRelation('sections') as $section) {
            if ($section->is_active) {
                $html .= $this->sections->renderSection($section, $context->market, $context->locale);
            }
        }

        return FrontPageData::view(app(FrontViewResolver::class)->resolve('components.preview', 'admin.block-components.preview'), ['componentName' => (string) $translation->getAttribute('title'), 'componentHtml' => $html, 'currentMarket' => $context->market, 'currentLocale' => $context->locale]);
    }
}

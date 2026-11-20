<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionRendererService;

/**
 * Renders the curated intro of a filter page.
 *
 * The intro is composed with the CMS block manager, so it is stored as the same
 * sections + translations shape as pages/posts/products and rendered with the
 * shared {@see SectionRendererService} — animation, styling and every active
 * block type work exactly as elsewhere in the CMS.
 */
final class FilterIntroRenderer
{
    public function __construct(
        private readonly SectionRendererService $renderer,
    ) {}

    /**
     * Intro HTML for the given market + locale ('' when nothing curated).
     */
    public function render(?FilterPage $page, ?Market $market = null, ?string $locale = null): string
    {
        if ($page === null) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();

        try {
            $sections = $page->sections()
                ->where('is_active', true)
                ->with('translations')
                ->orderBy('order')
                ->get();
        } catch (\Throwable) {
            return '';
        }

        $html = '';

        foreach ($sections as $section) {
            $html .= $this->renderer->renderSection($section, $market, $locale);
        }

        return $html;
    }

    /**
     * Plain-text projection of the intro (agents, meta description fallbacks).
     */
    public function text(?FilterPage $page, ?Market $market = null, ?string $locale = null): string
    {
        $html = $this->render($page, $market, $locale);

        if ($html === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '';

        return trim($text);
    }
}

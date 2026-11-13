<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Blocks\BlockRegistry;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SectionRendererService
{
    public function __construct(
        protected BlockRegistryInterface $blockRegistry
    ) {}

    /**
     * Renders a PageSection, PostSection, or ProductSection with its styling manifest, fullwidth wrapper, and scroll-magic animations.
     */
    /** @param array<string, mixed> $section */
    public function renderSection(Model|array $section, ?Market $market = null, ?string $locale = null): string
    {
        $render = fn (): string => $this->renderResolvedSection($section, $market, $locale);

        return $this->blockRegistry instanceof BlockRegistry ? $this->blockRegistry->withinAvailability($render) : $render();
    }

    /** @param array<string,mixed>|Model $section */
    private function renderResolvedSection(Model|array $section, ?Market $market, ?string $locale): string
    {
        $locale = $locale ?? app()->getLocale();

        // Stable id so the front can deep-link to this exact block in the
        // backoffice editor (`?section=<id>`), robust against reordering.
        $sectionId = is_array($section)
            ? (int) ($section['id'] ?? 0)
            : (int) $section->getKey();
        $sectionAttr = $sectionId > 0 ? ' data-cms-section="'.$sectionId.'"' : '';

        if (is_array($section)) {
            $content = $section;
            $styling = (array) ($section['styling_json'] ?? $section);
            $type = (string) ($section['type'] ?? 'text');
        } else {
            $translation = PublicTranslation::resolve($section, $locale, $market);
            if ($translation === null) {
                return '';
            }

            $content = (array) $translation->getAttribute('content_json');
            $styling = (array) ($section->styling_json ?? []);
            $type = (string) ($section->type ?? ($content['type'] ?? 'text'));
        }

        // 1. Canonical contract: the localized content wins over the master
        //    payload for every field (section and blocks), and the columns are
        //    read through DbCms\Core\Sections\SectionLayout, so the editor, the
        //    API and the front office agree.
        $resolved = SectionAppearance::sanitize(SectionLayout::resolve($styling, $content));
        $resolved['type'] = $type;

        $columnsLayout = (string) ($resolved['columns_layout'] ?? '1');
        $columns = SectionLayout::columns($resolved);

        if ($columns !== []) {
            $innerHtml = $this->renderColumns($columnsLayout, $columns, $market, $locale, (string) ($resolved['id'] ?? ($sectionId > 0 ? $sectionId : '')));
        } else {
            $innerHtml = $this->renderBlockContent($type, $resolved, $market, $locale, false);
        }

        if (trim($innerHtml) === '') {
            return '';
        }

        return $this->decorate($innerHtml, $resolved, $sectionAttr);
    }

    /** @param array<string,mixed> $resolved */
    public function decorate(string $innerHtml, array $resolved, string $sectionAttr = ''): string
    {
        $attributes = [];
        if (preg_match('/data-cms-section="([0-9]+)"/', $sectionAttr, $match)) {
            $attributes['data-cms-section'] = $match[1];
        }
        $attributes['data-cms-node'] = (string) ($resolved['id'] ?? $attributes['data-cms-section'] ?? '');

        return app(BlockRenderer::class)->presentation($innerHtml, $resolved, 'section', $attributes);
    }

    /**
     * Renders a multi-column layout with individual column blocks.
     *
     * @param  array<int, mixed>  $columns
     */
    protected function renderColumns(string $layout, array $columns, ?Market $market = null, ?string $locale = null, string $owner = ''): string
    {
        $gridClass = match ($layout) {
            '2' => 'grid grid-cols-1 md:grid-cols-2 gap-8 items-start',
            '1-2' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '2-1' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '3' => 'grid grid-cols-1 md:grid-cols-3 gap-8 items-start',
            '4' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start',
            default => 'grid grid-cols-1 gap-8 items-start',
        };

        $colsHtml = [];
        foreach ($columns as $idx => $col) {
            $colData = (array) $col;
            $colSpan = match ($layout) {
                '1-2' => ($idx === 1 ? 'md:col-span-2' : 'col-span-1'),
                '2-1' => ($idx === 0 ? 'md:col-span-2' : 'col-span-1'),
                default => 'col-span-1',
            };

            $colBlocks = (array) ($colData['blocks'] ?? []);
            $colBlocksHtml = [];
            foreach ($colBlocks as $subBlock) {
                if (is_array($subBlock)) {
                    $bType = (string) ($subBlock['type'] ?? 'text');
                    $colBlocksHtml[] = $this->renderBlockContent($bType, $subBlock, $market, $locale);
                }
            }

            $blocksRendered = implode("\n", $colBlocksHtml);
            if (trim($blocksRendered) === '') {
                continue;
            }

            $colsHtml[] = app(BlockRenderer::class)->presentation($blocksRendered, (array) ($colData['style'] ?? []), 'column', ['class' => $colSpan, 'data-cms-column-index' => (int) $idx, 'data-cms-column-owner' => $owner]);
        }

        if ($colsHtml === []) {
            return '';
        }
        $allColsHtml = implode("\n", $colsHtml);

        return <<<HTML
        <div class="cms-section-grid {$gridClass} w-full">
            {$allColsHtml}
        </div>
        HTML;
    }

    /**
     * Renders inner HTML of a single block based on its content type.
     *
     * @param  array<string, mixed>  $content
     */
    public function renderBlockContent(string $type, array $content, ?Market $market = null, ?string $locale = null, bool $decorateLegacy = true): string
    {
        try {
            return app(BlockRenderer::class)->render($type, $content, $market, $locale, $decorateLegacy);
        } catch (\Throwable $error) {
            Log::warning("Block '{$type}' failed to render: {$error->getMessage()}");

            return '';
        }
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

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

        $columnsLayout = (string) ($resolved['columns_layout'] ?? '1');
        $columns = SectionLayout::columns($resolved);

        if ($columns !== []) {
            $innerHtml = $this->renderColumns($columnsLayout, $columns, $market, $locale);
        } else {
            $innerHtml = $this->renderBlockContent($type, $resolved, $market, $locale);
        }

        if (trim($innerHtml) === '') {
            return '';
        }

        // 2. Animation & styling attributes (same resolved payload)
        $scrollMagic = (array) ($resolved['scroll_magic'] ?? []);
        $behavior = (string) ($resolved['animation'] ?? ($scrollMagic['behavior'] ?? 'fadeInUp'));
        $behavior = htmlspecialchars($behavior, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $delay = (string) ($resolved['animation_delay'] ?? ($scrollMagic['delay'] ?? '0.2s'));
        $delay = htmlspecialchars($delay, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $duration = (string) ($resolved['animation_duration'] ?? ($scrollMagic['duration'] ?? '1s'));
        $duration = htmlspecialchars($duration, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $containerStyle = (string) ($resolved['container_style'] ?? 'cms-card');
        $bgType = (string) ($resolved['bg_type'] ?? 'none');

        $scrollMagicAttr = $behavior !== 'none' ? "data-scroll-magic=\"{$behavior}\"" : 'data-scroll-magic';
        $scrollAttrsList = [
            $scrollMagicAttr,
            "data-scroll-effect=\"{$behavior}\"",
            "data-scroll-delay=\"{$delay}\"",
            "data-scroll-duration=\"{$duration}\"",
        ];

        if (isset($scrollMagic['scrub']) && is_string($scrollMagic['scrub'])) {
            $scrub = htmlspecialchars($scrollMagic['scrub'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $scrollAttrsList[] = "data-scroll-scrub=\"{$scrub}\"";
        }
        if ($behavior === 'video-playback' || $bgType === 'video') {
            $scrollAttrsList[] = 'data-scroll-video="playback"';
        }

        // Un fondo con comportamiento parallax necesita el motor de scroll,
        // aunque la animación de entrada no lo pidiera.
        if ($bgType === 'image' && ($resolved['bg_behavior'] ?? 'normal') === 'parallax'
            && ! in_array('data-scroll-scrub="parallax"', $scrollAttrsList, true)) {
            $scrollAttrsList[] = 'data-scroll-scrub="parallax"';
        }

        $scrollAttrs = implode(' ', $scrollAttrsList);

        $styleClass = match ($containerStyle) {
            'cyber-glass' => 'cms-card backdrop-blur-xl border cms-border-accent',
            'neon-glow' => 'cms-card  border cms-border-accent',
            'transparent' => 'bg-transparent border-0',
            'full-width-dark' => 'cms-surface-2 border-y cms-border',
            default => 'cms-card',
        };

        // Inline CSS styling (Background color & Borders)
        $inlineStyles = [];
        $bgColor = (string) ($resolved['bg_color'] ?? '');
        if (! empty($bgColor)) {
            $inlineStyles[] = "background-color: {$bgColor};";
        }

        $borderTop = $resolved['border_top'] ?? null;
        if (is_array($borderTop) && ! empty($borderTop['enabled'])) {
            $bWidth = htmlspecialchars((string) ($borderTop['width'] ?? '1px'));
            $bStyle = htmlspecialchars((string) ($borderTop['style'] ?? 'solid'));
            $bColor = htmlspecialchars((string) ($borderTop['color'] ?? 'var(--cms-accent)'));
            $inlineStyles[] = "border-top: {$bWidth} {$bStyle} {$bColor};";
        }

        $borderBottom = $resolved['border_bottom'] ?? null;
        if (is_array($borderBottom) && ! empty($borderBottom['enabled'])) {
            $bWidth = htmlspecialchars((string) ($borderBottom['width'] ?? '1px'));
            $bStyle = htmlspecialchars((string) ($borderBottom['style'] ?? 'solid'));
            $bColor = htmlspecialchars((string) ($borderBottom['color'] ?? 'var(--cms-accent)'));
            $inlineStyles[] = "border-bottom: {$bWidth} {$bStyle} {$bColor};";
        }

        $styleAttr = ! empty($inlineStyles) ? 'style="'.htmlspecialchars(implode(' ', $inlineStyles), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"' : '';

        // Container Width
        $contentWidth = (string) ($resolved['content_width'] ?? 'centered');
        $innerContainerClass = match ($contentWidth) {
            'full' => 'w-full relative z-10',
            'narrow' => 'max-w-4xl mx-auto relative z-10',
            default => 'max-w-7xl mx-auto relative z-10',
        };

        // Background Media (Image / Video)
        $bgMediaHtml = '';
        if ($bgType === 'image') {
            $imageUrl = htmlspecialchars((string) ($resolved['bg_image_url'] ?? ''));
            $overlay = htmlspecialchars((string) ($resolved['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)'));
            if (! empty($imageUrl)) {
                $bgMediaHtml = $this->renderImageBackground($imageUrl, $overlay, (string) ($resolved['bg_behavior'] ?? 'normal'));
            }
        } elseif ($bgType === 'video') {
            $videoUrl = htmlspecialchars((string) ($resolved['bg_video_url'] ?? ''));
            $poster = htmlspecialchars((string) ($resolved['bg_video_poster'] ?? ''));
            $overlay = htmlspecialchars((string) ($resolved['bg_video_overlay'] ?? 'rgba(0,0,0,0.5)'));
            $posterAttr = ! empty($poster) ? "poster=\"{$poster}\"" : '';
            if (! empty($videoUrl)) {
                $bgMediaHtml = <<<HTML
                <div class="absolute inset-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                    <video class="cms-bg-video w-full h-full object-cover" autoplay loop muted playsinline {$posterAttr}>
                        <source src="{$videoUrl}" type="video/mp4">
                    </video>
                    <div class="absolute inset-0" style="background-color: {$overlay};"></div>
                </div>
                HTML;
            }
        }

        // Top & Bottom SVG Shape Dividers
        $topDividerConfig = $resolved['divider_top'] ?? null;
        $topDivider = is_array($topDividerConfig) ? $this->renderShapeDivider('top', $topDividerConfig) : '';

        $bottomDividerConfig = $resolved['divider_bottom'] ?? null;
        $bottomDivider = is_array($bottomDividerConfig) ? $this->renderShapeDivider('bottom', $bottomDividerConfig) : '';

        return <<<HTML
        <section class="w-full my-8 relative overflow-hidden {$styleClass} p-6 sm:p-10 transition-all" {$styleAttr} {$scrollAttrs}{$sectionAttr}>
            {$bgMediaHtml}
            {$topDivider}
            <div class="{$innerContainerClass}">
                {$innerHtml}
            </div>
            {$bottomDivider}
        </section>
        HTML;
    }

    /**
     * Renders an image background honoring its behaviour.
     *
     * `normal` paints it as a CSS background; `fixed` pins it to the viewport
     * (`background-attachment: fixed`); `parallax` emits a real `<img>` layer
     * that the shared scroll engine translates (`data-scroll-scrub="parallax"`).
     */
    protected function renderImageBackground(string $imageUrl, string $overlay, string $behavior): string
    {
        if ($behavior === 'parallax') {
            // The section wrapper already carries `data-scroll-scrub="parallax"`
            // (see the scroll_magic payload); the engine moves `.cms-bg-layer`.
            return <<<HTML
            <div class="absolute inset-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                <img src="{$imageUrl}" alt="" aria-hidden="true" class="cms-bg-layer w-full h-full object-cover">
                <div class="absolute inset-0" style="background-color: {$overlay};"></div>
            </div>
            HTML;
        }

        $attachment = $behavior === 'fixed' ? 'background-attachment: fixed;' : '';

        return <<<HTML
            <div class="absolute inset-0 w-full h-full bg-cover bg-center -z-10" style="background-image: url('{$imageUrl}'); {$attachment}"></div>
            <div class="absolute inset-0 -z-10 pointer-events-none" style="background-color: {$overlay};"></div>
        HTML;
    }

    /**
     * Renders a multi-column layout with individual column blocks.
     *
     * @param  array<int, mixed>  $columns
     */
    protected function renderColumns(string $layout, array $columns, ?Market $market = null, ?string $locale = null): string
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

            // Column background: same contract as the section, applied to the
            // column wrapper (this is what makes "background" meaningful on a
            // column instead of only on the whole row).
            [$colStyleAttr, $colBgHtml, $colScrollAttr] = $this->columnBackgroundAttributes($colData);

            $colsHtml[] = <<<HTML
            <div class="cms-column {$colSpan} space-y-6 w-full relative overflow-hidden" {$colStyleAttr} {$colScrollAttr}>
                {$colBgHtml}
                <div class="relative z-10 space-y-6">
                    {$blocksRendered}
                </div>
            </div>
            HTML;
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
     * Resolves the background attributes of a single column.
     *
     * Columns support the same background contract as sections (color, image,
     * overlay and behaviour), which is why the appearance modal offers the
     * `Column` scope.
     *
     * @param  array<string, mixed>  $colData
     * @return array{0: string, 1: string, 2: string} [style attribute, background html, scroll attribute]
     */
    protected function columnBackgroundAttributes(array $colData): array
    {
        $style = SectionAppearance::sanitize((array) ($colData['style'] ?? []));
        $bgType = (string) ($style['bg_type'] ?? 'none');
        $inline = [];
        $bgHtml = '';
        $scrollAttr = '';

        $bgColor = (string) ($style['bg_color'] ?? '');

        if ($bgColor !== '') {
            $inline[] = 'background-color: '.htmlspecialchars($bgColor).';';
        }

        if ($bgType === 'image') {
            $imageUrl = htmlspecialchars((string) ($style['bg_image_url'] ?? ''));
            $overlay = htmlspecialchars((string) ($style['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)'));
            $behavior = (string) ($style['bg_behavior'] ?? 'normal');

            if ($imageUrl !== '') {
                $bgHtml = $this->renderImageBackground($imageUrl, $overlay, $behavior);

                if ($behavior === 'parallax') {
                    $scrollAttr = 'data-scroll-scrub="parallax"';
                }
            }
        }

        // Padding/border of the column row.
        $borderTop = $style['border_top'] ?? null;
        if (is_array($borderTop) && ! empty($borderTop['enabled'])) {
            $inline[] = 'border-top: '.htmlspecialchars((string) ($borderTop['width'] ?? '1px')).' '.htmlspecialchars((string) ($borderTop['style'] ?? 'solid')).' '.htmlspecialchars((string) ($borderTop['color'] ?? 'var(--cms-accent)')).';';
        }

        $borderBottom = $style['border_bottom'] ?? null;
        if (is_array($borderBottom) && ! empty($borderBottom['enabled'])) {
            $inline[] = 'border-bottom: '.htmlspecialchars((string) ($borderBottom['width'] ?? '1px')).' '.htmlspecialchars((string) ($borderBottom['style'] ?? 'solid')).' '.htmlspecialchars((string) ($borderBottom['color'] ?? 'var(--cms-accent)')).';';
        }

        $padding = (string) ($style['padding'] ?? '');
        if ($padding !== '') {
            $inline[] = 'padding: '.htmlspecialchars($padding).';';
        }

        $styleAttr = $inline !== [] ? 'style="'.implode(' ', $inline).'"' : '';

        return [$styleAttr, $bgHtml, $scrollAttr];
    }

    /**
     * Renders inner HTML of a single block based on its content type.
     *
     * @param  array<string, mixed>  $content
     */
    public function renderBlockContent(string $type, array $content, ?Market $market = null, ?string $locale = null): string
    {
        try {
            return $this->blockRegistry->render($type, $content, $market, $locale);
        } catch (\Throwable $error) {
            Log::warning("Block '{$type}' failed to render: {$error->getMessage()}");

            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function renderShapeDivider(string $position, array $config): string
    {
        $type = (string) ($config['type'] ?? 'none');
        if ($type === 'none') {
            return '';
        }

        $color = htmlspecialchars((string) ($config['color'] ?? 'var(--cms-border)'));
        $height = (int) ($config['height'] ?? 60);
        $flip = ! empty($config['flip']);
        $transform = $flip ? 'transform: scaleX(-1);' : '';
        $posClass = $position === 'top' ? 'top-0' : 'bottom-0';

        $svgContent = match ($type) {
            'waves' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0 C150,90 350,-40 500,60 C650,160 900,10 1200,40 L1200,{$height} L0,{$height} Z" fill="{$color}"></path>
            </svg>
            SVG,
            'tilt' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M1200 0L0 120V0h1200z" fill="{$color}"></path>
            </svg>
            SVG,
            'curve' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M600,112 C268.63,112 0,0 0,0 L1200,0 C1200,0 931.37,112 600,112 Z" fill="{$color}"></path>
            </svg>
            SVG,
            'triangle' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0 0l600 120 600-120v120H0z" fill="{$color}"></path>
            </svg>
            SVG,
            'spikes' => <<<SVG
            <svg class="relative block w-full" style="height: {$height}px;" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0 0l100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120 100 120 100-120v120H0z" fill="{$color}"></path>
            </svg>
            SVG,
            default => '',
        };

        if (empty($svgContent)) {
            return '';
        }

        return <<<HTML
        <div class="absolute left-0 right-0 w-full overflow-hidden leading-none pointer-events-none z-10 {$posClass}" style="height: {$height}px; {$transform}">
            {$svgContent}
        </div>
        HTML;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Blocks\Contracts\SearchableBlockReferencesInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Language;
use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Sections\SectionLayout;

/** Prepares picker definitions and dynamic fields before rendering Blade. */
final class BlockEditorCatalogue
{
    public function __construct(
        private readonly BlockRegistryInterface $blocks,
        private readonly SectionEditorService $sections,
    ) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        $active = $this->blocks->getActiveBlocks();
        $locales = array_values(array_map(strval(...), Language::query()->where('is_active', true)->orderBy('id')->pluck('code')->all()));
        $types = [];
        $dynamic = [];
        foreach ($active as $block) {
            // Owned references are fetched with explicit locale/market and bounded pages.
            $data = $block instanceof SearchableBlockReferencesInterface ? [] : $block->getEditorData();
            $dynamic = array_replace($dynamic, $data);
            $schema = $block->getEditorSchema();
            foreach ($schema as &$field) {
                $source = $field['options_source'] ?? null;
                if (is_string($source)) {
                    $field['options'] = (array) ($field['options'] ?? []) + array_column((array) ($data[$source] ?? []), (string) $field['option_label'], (string) $field['option_value']);
                }
            }
            unset($field);
            $rawCategory = $block->getCategory();
            $types[] = [
                'type' => $block->getType(),
                'name' => $block->getName(),
                'icon' => $block->getIcon(),
                'category' => match (true) {
                    in_array($rawCategory, ['dynamic', 'widget', 'interactive'], true) => 'dynamic',
                    $rawCategory === 'layout' => 'layout',
                    default => 'content',
                },
                'raw_category' => $rawCategory,
                'desc' => $block->getDescription(),
                'fullWidth' => $block->isFullWidth(),
                'defaultPayload' => $block->getDefaultPayload(),
                'defaultPayloadByLocale' => $this->examplesByLocale($block, $locales),
                'composable' => $block instanceof ComposableBlockInterface,
                'retiredPayloadKeys' => $block instanceof BlockPayloadCompatibilityInterface ? $block->retiredPayloadKeys() : [],
                'schema' => $schema,
                'editorView' => $block->getEditorView(),
            ];
        }

        $contextMarket = app(LocalizationServiceInterface::class)->activeMarkets()->first();

        return ['activeBlockTypes' => $active, 'dynamicPayloads' => $dynamic, 'availableTypesJs' => $types,
            'blockAppearanceProperties' => BlockStyle::properties(),
            'blockAppearanceClasses' => array_keys((array) config('cms.blocks.classes', [])),
            'blockHtmlClasses' => BlockStyle::htmlClassOptions(),
            'blockHtmlClassMax' => max(1, (int) config('cms.blocks.html_class_max', 16)),
            'blockPresentationProperties' => SectionAppearance::properties(),
            'blockAppearanceVariables' => BlockStyle::themeVariables(),
            'blockPreviewUrl' => route('admin.blocks.preview'), 'blockReferenceUrl' => route('admin.blocks.references'),
            'blockEditorMarket' => $contextMarket?->code,
            'blockEditorMarkets' => app(LocalizationServiceInterface::class)->activeMarkets()->pluck('code', 'id')->all(),
            'blockSources' => app(BlockSources::class)->describe(),
            'blockTemplates' => $this->publishedTemplates($types),
        ];
    }

    /**
     * Published Plantillas for the picker. Omitted when any nested type is unavailable.
     *
     * @param  list<array<string,mixed>>  $types
     * @return list<array{id:int,title:string,slug:?string,section_count:int,preview_url:string,sectionsByLocale:array<string,list<array<string,mixed>>>}>
     */
    private function publishedTemplates(array $types): array
    {
        $available = [];
        foreach ($types as $type) {
            $available[(string) $type['type']] = $type;
        }

        $locales = Language::query()->where('is_active', true)->orderBy('id')->pluck('code')->all();
        $templates = BlockComponent::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->with(['translations', 'sections.translations'])
            ->orderBy('id')
            ->get();

        $listing = [];
        foreach ($templates as $template) {
            $sectionsByLocale = $this->sections->payload($template);
            $usable = false;
            foreach ($locales as $locale) {
                $rows = $sectionsByLocale[$locale] ?? [];
                if ($rows === []) {
                    continue;
                }
                if ($this->compositionAvailable($rows, $available)) {
                    $usable = true;
                } else {
                    unset($sectionsByLocale[$locale]);
                }
            }
            if (! $usable || $sectionsByLocale === []) {
                continue;
            }

            $title = $template->translations->firstWhere('locale', $locales[0] ?? 'es')->title
                ?? $template->translations->first()->title
                ?? ('#'.$template->id);
            $slug = $template->translations->firstWhere('locale', $locales[0] ?? 'es')->slug
                ?? $template->translations->first()?->slug;

            $primaryLocale = (string) ($locales[0] ?? 'es');
            $sectionCount = count($sectionsByLocale[$primaryLocale] ?? $sectionsByLocale[array_key_first($sectionsByLocale)] ?? []);

            $listing[] = [
                'id' => (int) $template->id,
                'title' => (string) $title,
                'slug' => $slug !== null ? (string) $slug : null,
                'section_count' => $sectionCount,
                'preview_url' => route('admin.block-components.preview', $template),
                'sectionsByLocale' => $sectionsByLocale,
            ];
        }

        return $listing;
    }

    /**
     * @param  list<array<string,mixed>>  $sections
     * @param  array<string,array<string,mixed>>  $available
     */
    private function compositionAvailable(array $sections, array $available): bool
    {
        foreach ($sections as $section) {
            foreach (SectionLayout::blocks($section) as $block) {
                $type = (string) ($block['type'] ?? '');
                if ($type === '' || ! isset($available[$type])) {
                    return false;
                }
                $retired = (array) ($available[$type]['retiredPayloadKeys'] ?? []);
                foreach ($retired as $key) {
                    if (is_string($key) && $this->payloadHasKey($block, $key)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $locales
     * @return array<string,array<string,mixed>>
     */
    private function examplesByLocale(BlockTypeInterface $block, array $locales): array
    {
        $examples = [];
        foreach ($locales as $locale) {
            $examples[$locale] = $block instanceof AbstractBlockType ? $block->getExamplePayload($locale) : $block->getDefaultPayload();
        }

        return $examples;
    }

    /** @param array<string,mixed> $payload */
    private function payloadHasKey(array $payload, string $key): bool
    {
        if (array_key_exists($key, $payload)) {
            return true;
        }
        foreach ($payload as $value) {
            if (is_array($value) && $this->payloadHasKey($value, $key)) {
                return true;
            }
        }

        return false;
    }
}

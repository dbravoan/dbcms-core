<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Blocks\Contracts\SearchableBlockReferencesInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Sections\SectionAppearance;

/** Prepares picker definitions and dynamic fields before rendering Blade. */
final class BlockEditorCatalogue
{
    public function __construct(private readonly BlockRegistryInterface $blocks) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        $active = $this->blocks->getActiveBlocks();
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
            $types[] = [
                'type' => $block->getType(),
                'name' => $block->getName(),
                'icon' => $block->getIcon(),
                'category' => in_array($block->getCategory(), ['dynamic', 'widget', 'interactive'], true) ? 'widget' : 'content',
                'raw_category' => $block->getCategory(),
                'desc' => $block->getDescription(),
                'fullWidth' => $block->isFullWidth(),
                'defaultPayload' => $block->getDefaultPayload(),
                'composable' => $block instanceof ComposableBlockInterface,
                'retiredPayloadKeys' => $block instanceof BlockPayloadCompatibilityInterface ? $block->retiredPayloadKeys() : [],
                'schema' => $schema,
                'editorView' => $block->getEditorView(),
            ];
        }

        $presets = app(BlockPresets::class);
        $contextMarket = app(LocalizationServiceInterface::class)->activeMarkets()->first();

        return ['activeBlockTypes' => $active, 'dynamicPayloads' => $dynamic, 'availableTypesJs' => $types,
            'blockAppearanceProperties' => BlockStyle::properties(), 'blockAppearanceClasses' => array_keys((array) config('cms.blocks.classes', [])),
            'blockPresentationProperties' => SectionAppearance::properties(),
            'blockAppearanceVariables' => BlockStyle::themeVariables(),
            'blockPreviewUrl' => route('admin.blocks.preview'), 'blockReferenceUrl' => route('admin.blocks.references'),
            'blockEditorMarket' => $contextMarket?->code,
            'blockEditorMarkets' => app(LocalizationServiceInterface::class)->activeMarkets()->pluck('code', 'id')->all(),
            'blockSources' => app(BlockSources::class)->describe(),
            'blockPresets' => array_map(fn (array $preset): array => $preset + ['payload' => $presets->expand($preset['id'])], $presets->listing()),
        ];
    }
}

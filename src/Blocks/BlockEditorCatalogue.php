<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;

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
            $data = $block->getEditorData();
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
                'schema' => $schema,
                'editorView' => $block->getEditorView(),
            ];
        }

        return ['activeBlockTypes' => $active, 'dynamicPayloads' => $dynamic, 'availableTypesJs' => $types];
    }
}

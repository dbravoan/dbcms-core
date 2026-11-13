<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\BlockPresets;
use DbCms\Core\Services\Capabilities\EntityReadService;

final class BlockPresetsTool extends AbstractCapabilityTool
{
    public function __construct(private readonly BlockPresets $presets, private readonly BlockCatalogService $catalog, private readonly EntityReadService $entities) {}

    public function name(): string
    {
        return 'cms_block_presets';
    }

    public function description(): string
    {
        return 'Lists available editable compositions, or expands one preset into ordinary block nodes. Does not create or publish content. Reuse these templates instead of reconstructing their full structure in a model prompt.';
    }

    public function properties(): array
    {
        return ['entity_type' => ['type' => 'string'], 'entity_id' => ['type' => 'integer', 'minimum' => 1], 'locale' => ['type' => 'string'], 'market' => ['type' => 'string'], 'preset' => ['type' => 'string']];
    }

    public function required(): array
    {
        return ['entity_type', 'locale', 'market'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->entities->schemaFor($this->string($args, 'entity_type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        $context = $this->catalog->describe($this->string($args, 'entity_type'), $this->string($args, 'locale'), $this->string($args, 'market'), isset($args['entity_id']) ? (int) $args['entity_id'] : null, null, 'summary', false)['context'];

        return ['context' => $context] + (isset($args['preset']) ? ['node' => $this->presets->expand((string) $args['preset'])] : ['presets' => $this->presets->listing()]);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Services\Capabilities\EntityReadService;

final class BlockTypesTool extends AbstractCapabilityTool
{
    public function __construct(private readonly BlockCatalogService $catalog, private readonly EntityReadService $entities) {}

    public function name(): string
    {
        return 'cms_block_types';
    }

    public function description(): string
    {
        return 'Describes active blocks, their editor fields, validation, defaults, translation paths, layout constraints and public reference choices in an explicit language and market. Call before composing sections; use only returned references. Blocks marked is_full_width belong at section level, never inside a column. Default content is an example, not factual evidence. Does not require an AI provider.';
    }

    public function properties(): array
    {
        return [
            'entity_type' => ['type' => 'string', 'description' => 'Authorized entity type that supports sections'],
            'entity_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Existing entity, when editing; omit for a new draft'],
            'locale' => ['type' => 'string', 'minLength' => 1],
            'market' => ['type' => 'string', 'minLength' => 1, 'description' => 'Active market code'],
            'block_type' => ['type' => 'string', 'description' => 'Optional exact block identifier; omit for all active blocks'],
        ];
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
        return $this->catalog->describe($this->string($args, 'entity_type'), $this->string($args, 'locale'), $this->string($args, 'market'), isset($args['entity_id']) ? (int) $args['entity_id'] : null, isset($args['block_type']) ? (string) $args['block_type'] : null);
    }
}

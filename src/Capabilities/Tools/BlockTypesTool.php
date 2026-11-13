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
        return 'Starts with compact summaries of active blocks. Request block_type for one schema/defaults, then cms_block_references for filtered public choices in an explicit language and market. Call before composing sections; use only returned references. Blocks marked is_full_width belong at section level, never inside a column. Default content is an example, not factual evidence. Does not require an AI provider.';
    }

    public function properties(): array
    {
        return [
            'entity_type' => ['type' => 'string', 'description' => 'Authorized entity type that supports sections'],
            'entity_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Existing entity, when editing; omit for a new draft'],
            'locale' => ['type' => 'string', 'minLength' => 1],
            'market' => ['type' => 'string', 'minLength' => 1, 'description' => 'Active market code'],
            'block_type' => ['type' => 'string', 'description' => 'Optional exact block identifier; omit for all active blocks'],
            'view' => ['type' => 'string', 'enum' => ['summary', 'detail'], 'description' => 'Use summary first, then detail for selected block types only.'],
            'include_references' => ['type' => 'boolean', 'description' => 'Set false and use cms_block_references for filtered reference pages.'],
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
        return $this->catalog->describe($this->string($args, 'entity_type'), $this->string($args, 'locale'), $this->string($args, 'market'), isset($args['entity_id']) ? (int) $args['entity_id'] : null, isset($args['block_type']) ? (string) $args['block_type'] : null, (string) ($args['view'] ?? (isset($args['block_type']) ? 'detail' : 'summary')), (bool) ($args['include_references'] ?? false));
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Services\Capabilities\EntityReadService;

final class BlockReferencesTool extends AbstractCapabilityTool
{
    public function __construct(private readonly BlockCatalogService $catalog, private readonly EntityReadService $entities) {}

    public function name(): string
    {
        return 'cms_block_references';
    }

    public function description(): string
    {
        return 'Searches a bounded page of valid public references for one block field source, in the authorized entity, language and market. Never load the complete content catalogue.';
    }

    public function properties(): array
    {
        return [
            'entity_type' => ['type' => 'string'], 'entity_id' => ['type' => 'integer', 'minimum' => 1],
            'locale' => ['type' => 'string'], 'market' => ['type' => 'string'], 'block_type' => ['type' => 'string'],
            'source' => ['type' => 'string'], 'search' => ['type' => 'string', 'maxLength' => 200],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50], 'offset' => ['type' => 'integer', 'minimum' => 0],
        ];
    }

    public function required(): array
    {
        return ['entity_type', 'locale', 'market', 'block_type', 'source'];
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
        return $this->catalog->references($this->string($args, 'entity_type'), $this->string($args, 'locale'), $this->string($args, 'market'), $this->string($args, 'block_type'), $this->string($args, 'source'), (string) ($args['search'] ?? ''), (int) ($args['limit'] ?? 20), (int) ($args['offset'] ?? 0), isset($args['entity_id']) ? (int) $args['entity_id'] : null);
    }
}

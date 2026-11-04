<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Entity;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;

/**
 * Deletes an entity (with its translations and sections).
 */
class EntityDeleteTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityMutationService $entities,
        private readonly EntityReadService $reader,
    ) {}

    public function name(): string
    {
        return 'cms_entity_delete';
    }

    public function description(): string
    {
        return 'Deletes an entity together with its translations after human approval in the backoffice. Returns a review reference; tool arguments cannot grant approval. Previous revisions stay in the content audit trail';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Entity id'],
        ];
    }

    public function required(): array
    {
        return ['type', 'id'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->reader->schemaFor($this->string($args, 'type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_DESTRUCTIVE;
    }

    public function handle(array $args): array
    {
        return $this->entities->delete(
            $this->string($args, 'type'),
            $args['id'] ?? 0,
        );
    }
}

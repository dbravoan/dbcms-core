<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Resource;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\ResourceCrudService;

/**
 * Deletes a platform resource after persisted human approval.
 */
class ResourceDeleteTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly ResourceCrudService $resources,
    ) {}

    public function name(): string
    {
        return 'cms_resource_delete';
    }

    public function description(): string
    {
        return 'Deletes a platform resource after human approval in the backoffice. Returns a review reference; tool arguments cannot grant approval';
    }

    public function properties(): array
    {
        return [
            'resource' => ['type' => 'string', 'description' => 'Resource key'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Row id'],
        ];
    }

    public function required(): array
    {
        return ['resource', 'id'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->resources->resolve($this->string($args, 'resource'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_DESTRUCTIVE;
    }

    public function handle(array $args): array
    {
        return $this->resources->delete($this->string($args, 'resource'), $args['id'] ?? 0);
    }
}

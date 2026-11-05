<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Resource;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\ResourceCrudService;

/**
 * lists rows of a platform resource with pagination, free-text search and exact-match column filters
 */
class ResourceListTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly ResourceCrudService $resources,
    ) {}

    public function name(): string
    {
        return 'cms_resource_list';
    }

    public function description(): string
    {
        return 'MCP: lists rows of a platform resource with pagination, free-text search and exact-match column filters';
    }

    public function properties(): array
    {
        return [
            'resource' => ['type' => 'string', 'description' => 'Resource key (market, language, media, menu, menu_item, notification, form_submission, order, user, seo_filter, module, audit, token)'],
            'search' => ['type' => 'string', 'description' => 'Free text over the searchable columns'],
            'where' => ['type' => 'object', 'description' => 'Exact-match filters, e.g. {"status": "pending"}'],
            'order' => ['type' => 'string', 'description' => 'asc|desc (default: the resource default)'],
            'per_page' => ['type' => 'integer', 'description' => 'Rows per page (default 20, max 100)'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1)'],
        ];
    }

    public function required(): array
    {
        return ['resource'];
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
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        return $this->resources->listing($this->string($args, 'resource'), $args);
    }
}

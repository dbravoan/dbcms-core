<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Entity;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\EntityReadService;

/**
 * Listings for every registered entity, filtered like the backoffice lists.
 */
class EntityListTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityReadService $entities,
    ) {}

    public function name(): string
    {
        return 'cms_entity_list';
    }

    public function description(): string
    {
        return 'MCP: lists entities of one type (posts, pages, products, categories, galleries, forms) with pagination, free-text search over the translatable fields, locale and status filters';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type, e.g. post, page, product, blog_category, shop_category, gallery, form'],
            'search' => ['type' => 'string', 'description' => 'Free text over the translatable fields (title, name, summary…)'],
            'locale' => ['type' => 'string', 'description' => 'Only entities translated into this locale'],
            'status' => ['type' => 'string', 'description' => 'published|draft (maps to is_published/is_active)'],
            'order_by' => ['type' => 'string', 'description' => 'Column to order by (default: id)'],
            'order' => ['type' => 'string', 'description' => 'asc|desc (default: desc)'],
            'per_page' => ['type' => 'integer', 'description' => 'Items per page (default 20, max 100)'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1)'],
        ];
    }

    public function required(): array
    {
        return ['type'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->schemaPermission($this->string($args, 'type')));
    }

    public function ability(): string
    {
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        return $this->entities->listing($this->string($args, 'type'), $args);
    }

    private function schemaPermission(string $type): ?string
    {
        return $this->entities->schemaFor($type)->permission;
    }
}

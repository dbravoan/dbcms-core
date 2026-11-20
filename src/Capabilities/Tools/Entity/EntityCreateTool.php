<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Entity;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;

/**
 * Creates an entity of any registered type, translations included.
 */
class EntityCreateTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityMutationService $entities,
        private readonly EntityReadService $reader,
    ) {}

    public function name(): string
    {
        return 'cms_entity_create';
    }

    public function description(): string
    {
        return 'MCP: creates an entity of any installed type (post, page, product, category, gallery, form) with one or more translations at once. Attributes are limited to the fields the backoffice form writes; use "publish": true to publish what supports it. Content under editorial review is queued for approval exactly like in the backoffice';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type: post, page, product, blog_category, shop_category, gallery, form'],
            'attributes' => ['type' => 'object', 'description' => 'Model attributes. For a Blade page, set template to a key from page.blade_templates in cms_entity_types.'],
            'translations' => ['type' => 'object', 'description' => 'Locale-keyed content. Blade pages accept template_data with the translatable fields declared in cms_entity_types. Send plain strings for text fields and {format, content} for rich_text fields. Use sections_json for pages and posts composed with blocks.'],
            'market_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Markets in which the content belongs'],
            'audit_action' => ['type' => 'string', 'enum' => ['draft', 'submit_for_review'], 'description' => 'Save a private draft or submit it to editorial review; never grants publication authority'],
            'audit_notes' => ['type' => 'string', 'description' => 'Optional editorial review notes'],
            'sections_json' => ['type' => ['object', 'string'], 'description' => 'Draft body as locale → ordered sections → columns → blocks. Discover the contract with cms_block_types. An encoded JSON object is also accepted.'],
            'publish' => ['type' => 'boolean', 'description' => 'Requires human approval; omit to create a draft'],
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
        return $this->safePermission(fn (): ?string => $this->reader->schemaFor($this->string($args, 'type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_WRITE;
    }

    public function handle(array $args): array
    {
        return $this->entities->create(
            $this->string($args, 'type'),
            $args,
            $this->actor(),
        );
    }
}

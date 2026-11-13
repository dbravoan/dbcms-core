<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use DbCms\Core\Capabilities\Resources\CapabilityResource;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Modules\ModuleManager;

/** Projects module-owned schema registrations into an actor's public catalog.
 */
final class CmsCapabilityPresentation implements CapabilityPresentationInterface
{
    public function __construct(private readonly EntitySchemaRegistryInterface $entities, private readonly CapabilityResourceRegistry $resources, private readonly CapabilityRegistry $capabilities, private readonly CapabilityAuthorizer $authorizer, private readonly ModuleManager $modules) {}

    /**
     * @param  array<string,mixed>  $args
     */
    public function reason(string $name, array $args, ActorContext $actor): ?string
    {
        $block = in_array($name, ['cms_block_types', 'cms_block_references', 'cms_block_presets'], true);
        $entity = $block || str_starts_with($name, 'cms_entity_') || in_array($name, ['cms_audit_approve', 'cms_audit_reject', 'cms_audit_rollback', 'cms_content_sections_get', 'cms_content_sections_sync', 'cms_content_sections_patch'], true);
        $resource = str_starts_with($name, 'cms_resource_');
        if (! $entity && ! $resource) {
            return null;
        }
        $key = $block ? 'entity_type' : ($entity ? 'type' : 'resource');
        $target = (string) ($args[$key] ?? '');
        if ($target === '') {
            return null;
        }
        $schemas = $entity ? $this->entities->all() : $this->resources->all();
        $schema = $schemas[$target] ?? null;
        if ($schema === null || ! $this->active($schema->model) || ($block && (! $schema instanceof EntitySchema || ! $schema->sectionable))) {
            return 'El módulo propietario no está disponible.';
        }
        if ($schema instanceof CapabilityResource) {
            $operation = str_replace('cms_resource_', '', $name);
            $allowed = match ($operation) {
                'create' => $schema->allowCreate, 'update' => $schema->allowUpdate, 'delete' => $schema->allowDelete, default => true
            };
            if (! $allowed) {
                return 'Operación no implementada para este recurso.';
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>|null
     */
    public function schema(string $name, array $schema, ActorContext $actor): ?array
    {
        $block = in_array($name, ['cms_block_types', 'cms_block_references', 'cms_block_presets'], true);
        $entity = $block || str_starts_with($name, 'cms_entity_') || in_array($name, ['cms_audit_approve', 'cms_audit_reject', 'cms_audit_rollback', 'cms_content_sections_get', 'cms_content_sections_sync', 'cms_content_sections_patch'], true);
        $resource = str_starts_with($name, 'cms_resource_');
        if (! $entity && ! $resource) {
            return $schema;
        }
        $choices = [];
        $ability = $this->capabilities->get($name)?->ability() ?? 'read';
        foreach ($entity ? $this->entities->all() : $this->resources->all() as $key => $target) {
            $args = [$block ? 'entity_type' : ($entity ? 'type' : 'resource') => $key];
            if ($this->active($target->model) && $this->authorizer->allowed($actor, $ability, $target->permission ?? 'access:admin') && $this->reason($name, $args, $actor) === null) {
                $choices[] = $key;
            }
        }
        if ($choices === []) {
            return null;
        }
        $key = $block ? 'entity_type' : ($entity ? 'type' : 'resource');
        if (isset($schema['parameters']['properties'][$key])) {
            $schema['parameters']['properties'][$key]['enum'] = $choices;
        }

        return $schema;
    }

    private function active(string $model): bool
    {
        $owner = $this->modules->ownerOfClass($model);

        return $owner === null || $this->modules->isModuleEnabled($owner);
    }
}

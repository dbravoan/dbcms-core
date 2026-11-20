<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Orchestration;

use DbCms\Core\Capabilities\Contracts\ContentRevisionInspectorInterface;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Binds approval to current content, translations and sections.
 */
final class ResourceFingerprint
{
    public function __construct(private readonly EntitySchemaRegistryInterface $entities, private readonly CapabilityResourceRegistry $resources) {}

    /**
     * @param  array<string,mixed>  $args
     */
    public function hash(string $capability, array $args, int $version): string
    {
        $model = $this->target($capability, $args);
        $snapshot = null;
        if ($model instanceof Model) {
            $snapshot = app(ContentSnapshotService::class)->capture($model);
            if (app()->bound(ContentRevisionInspectorInterface::class)) {
                $snapshot['pending_revision'] = app(ContentRevisionInspectorInterface::class)->fingerprint($model);
            }
        }

        return hash('sha256', json_encode($this->canonical([$version, $capability, $args, $snapshot]), JSON_THROW_ON_ERROR));
    }

    /**
     * Human-readable review data; excludes credentials and model internals.
     *
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    public function preview(string $capability, array $args): array
    {
        $entity = $this->target($capability, $args);
        if ($entity === null) {
            return [];
        }
        $attributes = $entity->getAttributes();
        $preview = ['id' => $entity->getKey(), 'name' => $attributes['name'] ?? $attributes['title'] ?? null];
        if (app()->bound(ContentRevisionInspectorInterface::class)) {
            $pending = app(ContentRevisionInspectorInterface::class)->preview($entity);
            if ($pending !== null) {
                return $this->visible($preview + $pending, array_values($entity->getHidden()));
            }
        }
        if (method_exists($entity, 'translations')) {
            $preview['translations'] = $entity->translations()->get()->toArray();
        }
        if (method_exists($entity, 'sections')) {
            $preview['sections'] = $entity->sections()->get()->toArray();
        }
        $snapshot = app(ContentSnapshotService::class)->capture($entity);
        $preview['attributes'] = $snapshot['attributes'];
        $preview['markets'] = $snapshot['market_pivots'];
        $preview['related_content'] = $snapshot['module_relations'] ?? [];

        return $this->visible($preview, array_values($entity->getHidden()));
    }

    /**
     * @param  array<array-key,mixed>  $value
     * @param  list<string>  $hidden
     * @return array<array-key,mixed>
     */
    private function visible(array $value, array $hidden): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && (in_array($key, $hidden, true) || preg_match('/password|secret|token|credential|(?:^|_)(?:api|access|private)_key|_hash$/i', $key) === 1)) {
                unset($value[$key]);
            } elseif (is_array($item)) {
                $value[$key] = $this->visible($item, $hidden);
            }
        }

        return $value;
    }

    /**
     * Atomic local publish/delete: the approved version is rechecked under the resource row lock.
     *
     * @param  array<string,mixed>  $args
     * @param  \Closure():array<string,mixed>  $execute
     * @return array<string,mixed>
     */
    public function guardLocalMutation(string $capability, array $args, int $version, string $binding, \Closure $execute): array
    {
        return DB::transaction(function () use ($capability, $args, $version, $binding, $execute): array {
            $model = $this->target($capability, $args);
            if ($model !== null) {
                $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();
            }
            if ($binding !== $this->hash($capability, $args, $version)) {
                return ['confirmation_required' => true, 'error' => 'El contenido cambió. Revisa y aprueba la nueva versión.'];
            }

            return $execute();
        });
    }

    /** @param array<string,mixed> $args */
    private function target(string $capability, array $args): ?Model
    {
        $type = (string) ($args['type'] ?? $args['entity_type'] ?? '');
        if ($type === '') {
            foreach (['post', 'page', 'product', 'category'] as $candidate) {
                if (str_ends_with($capability, '_'.$candidate)) {
                    $type = $candidate === 'category' ? 'blog_category' : $candidate;
                    break;
                }
            }
        }
        $schema = $this->entities->get($type);
        $resource = $this->resources->get((string) ($args['resource'] ?? ''));
        $modelClass = $schema !== null ? $schema->model : ($resource !== null ? $resource->model : null);
        $id = $args['id'] ?? $args['entity_id'] ?? $args[$type.'_id'] ?? null;

        return is_string($modelClass) && is_subclass_of($modelClass, Model::class) && (is_string($id) || is_int($id)) ? $modelClass::find($id) : null;
    }

    /**
     * @param  array<array-key,mixed>  $value
     * @return array<array-key,mixed>
     */
    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}

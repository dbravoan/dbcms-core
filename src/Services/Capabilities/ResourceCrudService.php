<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\Contracts\ResourceMutationHandlerInterface;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\Resources\CapabilityResource;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Services\Resources\ResourceMutationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Generic CRUD over the platform resources an agent may manage.
 *
 * One engine, described by {@see CapabilityResource} definitions, so markets,
 * languages, menus, media, orders, submissions, users, SEO landings and the
 * audit queue are all manageable without a bespoke tool — and validation rules
 * mirror the backoffice forms.
 */
class ResourceCrudService
{
    public function __construct(
        private readonly CapabilityResourceRegistry $resources,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function resources(): array
    {
        $described = [];

        foreach ($this->resources->all() as $resource) {
            $description = $resource->describe();
            $description['fillable'] = array_values(array_unique(array_merge($resource->fillable, array_filter(array_keys($this->rulesFor($resource)), static fn (string $key): bool => ! str_contains($key, '.')))));
            $described[] = $description;
        }

        return $described;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listing(string $key, array $filters = []): array
    {
        $resource = $this->resolve($key);

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $search = is_string($filters['search'] ?? null) ? trim((string) $filters['search']) : '';

        /** @var Builder<Model> $query */
        $query = $this->newModel($resource)->newQuery();

        if ($resource->scope !== null) {
            ($resource->scope)($query);
        }

        if ($search !== '' && $resource->searchable !== []) {
            $needle = '%'.$search.'%';

            $query->where(function (Builder $inner) use ($needle, $resource): void {
                foreach ($resource->searchable as $column) {
                    $inner->orWhere($column, 'like', $needle);
                }
            });
        }

        foreach (($filters['where'] ?? []) as $column => $value) {
            if (is_string($column) && in_array($column, $resource->listColumns(), true)) {
                $query->where($column, $value);
            }
        }

        $total = (clone $query)->count();

        $order = strtolower((string) ($filters['order'] ?? $resource->orderDirection)) === 'asc' ? 'asc' : 'desc';

        /** @var Collection<int, Model> $rows */
        $rows = $query
            ->orderBy($resource->orderBy, $order)
            ->forPage($page, $perPage)
            ->get();

        return [
            'resource' => $resource->key,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $rows->map(fn (Model $model): array => $this->payload($resource, $model))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $key, int|string $id): array
    {
        $resource = $this->resolve($key);

        return $this->payload($resource, $this->findModel($resource, $id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(string $key, array $attributes): array
    {
        $resource = $this->resolve($key);

        if (! $resource->allowCreate) {
            throw ValidationException::withMessages([
                'resource' => "The [{$resource->key}] resource is read-only for creation.",
            ]);
        }

        return $this->mutate(function () use ($resource, $attributes): array {
            $this->authorize($resource, 'write');
            $model = $this->newModel($resource);
            $handler = $this->handler($resource);
            $rules = $this->rulesFor($resource, model: $model);
            $attributes = Validator::make($attributes, $rules)->validate();
            $attributes = $handler?->prepare($model, $attributes, true) ?? $attributes;
            $attributes = Validator::make($attributes, $rules)->validate();
            $model->fill($this->persistable($resource, $model, $attributes));
            $model->save();
            $handler?->saved($model, $attributes, true);
            if ($resource->afterCreate !== null) {
                ($resource->afterCreate)($model, $attributes);
            }

            return $this->payload($resource, $model->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(string $key, int|string $id, array $attributes): array
    {
        $resource = $this->resolve($key);

        if (! $resource->allowUpdate) {
            throw ValidationException::withMessages([
                'resource' => "The [{$resource->key}] resource is read-only for updates.",
            ]);
        }

        return $this->mutate(function () use ($resource, $id, $attributes): array {
            $this->authorize($resource, 'write');
            $model = $this->findModel($resource, $id, lock: true);
            $handler = $this->handler($resource);
            $rules = $this->rulesFor($resource, updating: true, model: $model);
            $validated = Validator::make($attributes, $rules)->validate();
            $validated = $handler?->prepare($model, $validated, false) ?? $validated;
            $validated = Validator::make($validated, $rules)->validate();
            $persist = $this->persistable($resource, $model, $validated);
            if ($persist !== []) {
                $model->fill($persist)->save();
            }
            $handler?->saved($model, $validated, false);
            if ($resource->afterUpdate !== null) {
                ($resource->afterUpdate)($model, $validated);
            }

            return $this->payload($resource, $model->refresh());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $key, int|string $id): array
    {
        $resource = $this->resolve($key);

        if (! $resource->allowDelete) {
            throw ValidationException::withMessages([
                'resource' => "The [{$resource->key}] resource cannot be deleted.",
            ]);
        }

        return $this->mutate(function () use ($resource, $id): array {
            $this->authorize($resource, 'destructive');
            $model = $this->findModel($resource, $id, lock: true);
            $payload = $this->payload($resource, $model);
            $this->handler($resource)?->deleting($model);
            $model->delete();

            return ['deleted' => true, 'resource' => $resource->key, 'id' => $payload['id'] ?? $id];
        });
    }

    public function resolve(string $key): CapabilityResource
    {
        $resource = $this->resources->get($key);

        if ($resource === null) {
            throw ValidationException::withMessages([
                'resource' => "Unknown resource [{$key}]. Available: ".implode(', ', $this->resources->keys()).'.',
            ]);
        }

        return $resource;
    }

    /** @return array<string,mixed> */
    public function toggle(string $key, int|string $id): array
    {
        $resource = $this->resolve($key);
        if (! in_array('is_active', $resource->fillable, true)) {
            throw ValidationException::withMessages(['resource' => 'This resource has no activation flag.']);
        }

        return $this->mutate(function () use ($resource, $id): array {
            $model = $this->findModel($resource, $id, lock: true);

            return $this->update($resource->key, $id, ['is_active' => ! (bool) ($model->getAttributes()['is_active'] ?? false)]);
        });
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $key): string
    {
        /** @var class-string<Model> $class */
        $class = $this->resolve($key)->model;

        return $class;
    }

    private function findModel(CapabilityResource $resource, int|string $id, bool $lock = false): Model
    {
        /** @var Builder<Model> $query */
        $query = $this->newModel($resource)->newQuery();

        if ($resource->scope !== null) {
            ($resource->scope)($query);
        }

        if ($lock) {
            $query->lockForUpdate();
        }
        $model = $query->whereKey($id)->first();

        if (! $model instanceof Model) {
            throw ValidationException::withMessages([
                'id' => "No [{$resource->key}] found with id [{$id}].",
            ]);
        }

        return $model;
    }

    private function newModel(CapabilityResource $resource): Model
    {
        /** @var class-string<Model> $class */
        $class = $resource->model;

        return new $class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesFor(CapabilityResource $resource, bool $updating = false, ?Model $model = null): array
    {
        $source = array_replace($resource->rules, $this->handler($resource)?->rules($model ?? $this->newModel($resource), ! $updating) ?? []);
        if (! $updating) {
            return $source;
        }

        $rules = [];

        foreach ($source as $attribute => $rule) {
            $list = is_array($rule) ? $rule : explode('|', $rule);
            // A patch never makes a field mandatory.
            $list = array_values(array_filter($list, static fn (mixed $item): bool => $item !== 'required'));

            $rules[$attribute] = array_merge(['sometimes'], $list);
        }

        return $rules;
    }

    /**
     * @param  \Closure():array<string,mixed>  $operation
     * @return array<string,mixed>
     */
    private function mutate(\Closure $operation): array
    {
        try {
            return DB::transaction($operation);
        } catch (ResourceMutationException $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }
    }

    private function authorize(CapabilityResource $resource, string $ability): void
    {
        $actor = app(ExecutionContext::class)->actor ?? app(ActorContextFactory::class)->fromRequest(request(), 'web');
        abort_unless(app(CapabilityAuthorizer::class)->allowed($actor, $ability, $resource->permission), 403);
    }

    private function handler(CapabilityResource $resource): ?ResourceMutationHandlerInterface
    {
        if ($resource->mutationHandler === null) {
            return null;
        }
        $handler = app($resource->mutationHandler);
        if (! $handler instanceof ResourceMutationHandlerInterface) {
            throw new \LogicException('Resource handlers must implement the common mutation contract.');
        }

        return $handler;
    }

    /**
     * Only the model's own fillable attributes are persisted; anything else in
     * the resource definition is meant for the hooks (role_id, for instance).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function persistable(CapabilityResource $resource, Model $model, array $attributes): array
    {
        $modelFillable = $model->getFillable();
        $persist = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $resource->fillable, true) && in_array($key, $modelFillable, true)) {
                $persist[$key] = $value;
            }
        }

        return $persist;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CapabilityResource $resource, Model $model): array
    {
        $payload = ['id' => $model->getKey(), 'resource' => $resource->key];

        foreach ($resource->listColumns() as $column) {
            $payload[$column] = $model->getAttribute($column);
        }
        if (method_exists($model, 'translations')) {
            $payload['translations'] = $model->translations()->get()->toArray();
        }

        return $payload;
    }
}

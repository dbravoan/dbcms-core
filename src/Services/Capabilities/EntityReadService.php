<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Content\Contracts\EntityRelationPayloadHandlerInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Queries and serializes declared entities for administrative consumers. */
class EntityReadService
{
    public function __construct(private readonly EntitySchemaRegistryInterface $schemas, private readonly LocalizationServiceInterface $localization) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->schemas->all() as $schema) {
            $types[] = $this->describe($schema);
        }

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(EntitySchema $schema): array
    {
        return [
            'type' => $schema->type,
            'label' => $schema->label,
            'model' => class_basename($schema->model),
            'permission' => $schema->permission ?? 'access:admin',
            'sectionable' => $schema->sectionable,
            'searchable' => $schema->searchable,
            'translatable' => is_subclass_of($schema->model, TranslatableEntity::class),
            'roles' => $schema->fieldRoles(),
            'attributes' => $schema->fillableAttributes(),
            'translation_attributes' => array_values($schema->translationAttributes()),
            'description' => $schema->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listing(string $type, array $filters = []): array
    {
        $schema = $this->schemaFor($type);
        $model = $schema->newModel();

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $locale = $this->stringOrNull($filters['locale'] ?? null);
        $search = $this->stringOrNull($filters['search'] ?? null);
        $status = $this->stringOrNull($filters['status'] ?? null);

        /** @var Builder<Model> $query */
        $query = $model->newQuery();

        if ($model instanceof TranslatableEntity) {
            $query->with('translations');
        }

        if ($locale !== null && $model instanceof TranslatableEntity) {
            $query->whereHas('translations', static fn (Builder $q): Builder => $q->where('locale', $locale));
        }

        if ($search !== null && $model instanceof TranslatableEntity) {
            $needle = '%'.$search.'%';
            $columns = array_values($schema->translationAttributes());

            $query->whereHas('translations', static function (Builder $q) use ($needle, $columns): void {
                $q->where(function (Builder $inner) use ($needle, $columns): void {
                    foreach ($columns as $column) {
                        $inner->orWhere($column, 'like', $needle);
                    }
                });
            });
        }

        if ($status !== null) {
            $this->applyStatusFilter($query, $status);
        }

        $column = $this->listableColumn($model, $filters['order_by'] ?? null);
        $direction = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count();

        /** @var Collection<int, Model> $rows */
        $rows = $query
            ->orderBy($column, $direction)
            ->forPage($page, $perPage)
            ->get();

        return [
            'type' => $type,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $rows->map(fn (Model $entity): array => $this->payload($schema, $entity))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $type, int|string $id): array
    {
        $schema = $this->schemaFor($type);

        return $this->payload($schema, $this->resolve($schema, $id));
    }

    /**
     * Resolves the model instance of an entity type (used by the editorial and
     * block actions, which work on any sectionable entity).
     */
    public function resolveEntity(string $type, int|string $id): Model
    {
        return $this->resolve($this->schemaFor($type), $id);
    }

    public function schemaFor(string $type): EntitySchema
    {
        $schema = $this->schemas->get($type);

        if ($schema === null) {
            throw ValidationException::withMessages([
                'type' => "Unknown entity type [{$type}]. Available: ".implode(', ', $this->schemas->types()).'.',
            ]);
        }

        return $schema;
    }

    public function resolve(EntitySchema $schema, int|string $id, bool $lock = false): Model
    {
        $model = $schema->newModel();

        $query = $model->newQuery()->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $found = $query->first();

        if (! $found instanceof Model) {
            throw ValidationException::withMessages([
                'id' => "No [{$schema->type}] found with id [{$id}].",
            ]);
        }

        if ($found instanceof TranslatableEntity) {
            $found->load('translations');
        }

        return $found;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(EntitySchema $schema, Model $entity): array
    {
        $attributes = [];

        foreach ($schema->fillableAttributes() as $attribute) {
            $value = $entity->getAttribute($attribute);

            if ($value !== null) {
                $attributes[$attribute] = $value;
            }
        }

        $translations = [];

        if ($entity instanceof TranslatableEntity) {
            $entity->loadMissing('translations');

            /** @var Collection<int, Model> $rows */
            $rows = $entity->getRelation('translations');

            foreach ($rows as $translation) {
                $locale = (string) $translation->getAttribute('locale');
                $values = [];

                foreach ($translation->getFillable() as $attribute) {
                    $value = $translation->getAttribute($attribute);

                    if ($value !== null && $value !== '') {
                        $values[$attribute] = $value;
                    }
                }

                $translations[$locale] = $values;
            }
        }

        $urls = $this->urlsFor($schema, $translations);
        $handler = $schema->mutationHandler !== null ? app($schema->mutationHandler) : null;
        $relations = $handler instanceof EntityRelationPayloadHandlerInterface ? $handler->relationPayload($entity) : [];

        return [
            'id' => $entity->getKey(),
            'type' => $schema->type,
            'label' => $schema->label,
            'status' => $this->statusOf($entity),
            'attributes' => $attributes,
            'translations' => $translations,
            'relations' => $relations,
            'url' => $urls[(string) app()->getLocale()] ?? (reset($urls) ?: null),
            'urls' => $urls,
            'updated_at' => $entity->getAttribute('updated_at'),
        ];
    }

    private function statusOf(Model $entity): string
    {
        $attributes = $entity->getAttributes();
        foreach (['is_published', 'is_active'] as $flag) {
            if (array_key_exists($flag, $attributes)) {
                return filter_var($attributes[$flag], FILTER_VALIDATE_BOOLEAN) ? 'published' : 'draft';
            }
        }

        return 'active';
    }

    /**
     * Canonical URL of the entity in each locale it is translated into.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, string>
     */
    private function urlsFor(EntitySchema $schema, array $translations): array
    {
        if ($schema->urlPrefix === null) {
            return [];
        }

        $urls = [];

        foreach ($translations as $locale => $values) {
            $slug = (string) ($values['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $urls[(string) $locale] = $schema->urlPrefix === ''
                ? $this->localization->url('/'.$slug, null, $locale)
                : $this->localization->frontUrl($schema->urlPrefix, $slug, $locale);
        }

        return $urls;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        $published = in_array(strtolower($status), ['published', 'active'], true);

        foreach (['is_published', 'is_active'] as $flag) {
            if ($this->newModelClassHasColumn($query, $flag)) {
                $query->where($flag, $published);

                return;
            }
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function newModelClassHasColumn(Builder $query, string $column): bool
    {
        $model = $query->getModel();
        $table = $model->getTable();

        return $model->getConnection()->getSchemaBuilder()->hasColumn($table, $column);
    }

    private function listableColumn(Model $model, mixed $requested): string
    {
        $allowed = ['id', 'created_at', 'updated_at', 'position', 'order', 'sort_order'];

        if (is_string($requested) && in_array($requested, array_merge($allowed, $model->getFillable()), true)) {
            return $requested;
        }

        return 'id';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

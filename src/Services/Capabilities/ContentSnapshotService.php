<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Content\Contracts\EntityRevisionHandlerInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/** Trusted, server-captured revisions. Never accepts snapshot rows from callers. */
final class ContentSnapshotService
{
    public function __construct(private readonly EntitySchemaRegistryInterface $schemas) {}

    /**
     * Returns an in-memory model for editors; never saves the pending content.
     *
     * @param  array<string,mixed>  $snapshot
     */
    public function hydrate(Model $entity, array $snapshot): Model
    {
        $this->assertEntitySnapshot($entity, $snapshot);
        $draft = clone $entity;
        $draft->unsetRelations();
        $draft->setRawAttributes((array) $snapshot['attributes'], true);
        if (method_exists($draft, 'translations')) {
            $related = $draft->translations()->getRelated();
            $rows = array_map(static function (array $attributes) use ($related): Model {
                $row = $related->newInstance([], true);
                $row->setRawAttributes($attributes, true);

                return $row;
            }, (array) ($snapshot['translation_records'] ?? []));
            $draft->setRelation('translations', $related->newCollection($rows));
        }
        if ($draft instanceof Sectionable) {
            $class = $draft->sectionModelClass();
            $related = new $class;
            $rows = array_map(static function (array $record) use ($related): Model {
                $section = $related->newInstance([], true);
                $section->setRawAttributes((array) $record['attributes'], true);
                if (! method_exists($section, 'translations')) {
                    throw new \LogicException('Section models must expose translations.');
                }
                $translation = $section->translations()->getRelated();
                $translations = array_map(static function (array $attributes) use ($translation): Model {
                    $row = $translation->newInstance([], true);
                    $row->setRawAttributes($attributes, true);

                    return $row;
                }, (array) ($record['translations'] ?? []));
                $section->setRelation('translations', $translation->newCollection($translations));

                return $section;
            }, (array) ($snapshot['section_records'] ?? []));
            $draft->setRelation('sections', $related->newCollection($rows));
        }
        if (method_exists($draft, 'markets') && $draft->markets() instanceof BelongsToMany) {
            $relation = $draft->markets();
            $rows = $relation->getRelated()->newQuery()->whereKey((array) ($snapshot['market_ids'] ?? []))->get();
            foreach ($rows as $row) {
                foreach ((array) ($snapshot['market_pivots'] ?? []) as $pivot) {
                    if ((string) $pivot[$relation->getRelatedPivotKeyName()] === (string) $row->getKey()) {
                        $row->setRelation($relation->getPivotAccessor(), $relation->newExistingPivot($pivot));
                    }
                }
            }
            $draft->setRelation('markets', $rows);
        }

        if (array_key_exists('module_relations', $snapshot)) {
            $this->revisionHandler($draft)?->hydrateRevision($draft, (array) $snapshot['module_relations']);
        }

        return $draft;
    }

    /** @return array<string,mixed> */
    public function capture(Model $entity): array
    {
        $snapshot = ['snapshot_version' => 3, 'attributes' => $entity->getAttributes(), 'translation_records' => [], 'section_records' => [], 'market_ids' => [], 'market_pivots' => []];
        if (method_exists($entity, 'translations')) {
            $snapshot['translation_records'] = $entity->translations()->get()->map(static fn (Model $row): array => $row->getAttributes())->all();
        }
        if ($entity instanceof Sectionable) {
            $class = $entity->sectionModelClass();
            $snapshot['section_records'] = $class::query()->where($entity->sectionForeignKey(), $entity->getKey())->orderBy('id')->get()->map(static function (Model $row): array {
                if (! method_exists($row, 'translations')) {
                    throw new \LogicException('Section models must expose translations.');
                }

                return ['attributes' => $row->getAttributes(), 'translations' => $row->translations()->get()->map(static fn (Model $translation): array => $translation->getAttributes())->all()];
            })->all();
        }
        if (method_exists($entity, 'markets') && $entity->markets() instanceof BelongsToMany) {
            $relation = $entity->markets();
            $snapshot['market_ids'] = $relation->allRelatedIds()->sort()->values()->all();
            $snapshot['market_pivots'] = $relation->newPivotQuery()->orderBy($relation->getRelatedPivotKeyName())->get()->map(static fn (object $row): array => (array) $row)->all();
        }

        $handler = $this->revisionHandler($entity);
        if ($handler !== null) {
            $snapshot['module_relations'] = $handler->captureRevision($entity);
        }

        return $snapshot;
    }

    /** @param array<string,mixed> $snapshot */
    public function restore(Model $entity, array $snapshot): void
    {
        $this->assertEntitySnapshot($entity, $snapshot);
        DB::transaction(fn () => Model::withoutEvents(function () use ($entity, $snapshot): void {
            if ($entity instanceof Page) {
                $live = Page::query()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
                app(LegalPageService::class)->assertLegalSnapshot($live, $snapshot);
            }
            $attributes = (array) $snapshot['attributes'];
            DB::table($entity->getTable())->where($entity->getKeyName(), $entity->getKey())->update($attributes);
            if (method_exists($entity, 'translations')) {
                $relation = $entity->translations();
                $relation->delete();
                $rows = (array) ($snapshot['translation_records'] ?? []);
                if ($rows !== []) {
                    DB::table($relation->getRelated()->getTable())->insert($rows);
                }
            }
            if ($entity instanceof Sectionable) {
                $class = $entity->sectionModelClass();
                foreach ($class::query()->where($entity->sectionForeignKey(), $entity->getKey())->get() as $section) {
                    if (! method_exists($section, 'translations')) {
                        throw new \LogicException('Section models must expose translations.');
                    }
                    $section->translations()->delete();
                    $section->delete();
                }
                foreach ((array) ($snapshot['section_records'] ?? []) as $section) {
                    $model = new $class;
                    if (! method_exists($model, 'translations')) {
                        throw new \LogicException('Section models must expose translations.');
                    }
                    DB::table($model->getTable())->insert($section['attributes']);
                    if ($section['translations'] !== []) {
                        DB::table($model->translations()->getRelated()->getTable())->insert($section['translations']);
                    }
                }
            }
            if (method_exists($entity, 'markets') && $entity->markets() instanceof BelongsToMany) {
                $relation = $entity->markets();
                $relation->newPivotQuery()->delete();
                if ($snapshot['market_pivots'] !== []) {
                    DB::table($relation->getTable())->insert($snapshot['market_pivots']);
                }
            }
            if (array_key_exists('module_relations', $snapshot)) {
                $this->revisionHandler($entity)?->restoreRevision($entity, (array) $snapshot['module_relations']);
            }
            $entity->refresh();
        }));
    }

    /** @param array<string,mixed> $snapshot */
    public function assertValid(array $snapshot): void
    {
        if (($snapshot['snapshot_version'] ?? null) !== 3) {
            throw new \DomainException('Only canonical snapshot version 3 is supported.');
        }
        foreach (['attributes', 'translation_records', 'section_records', 'market_ids', 'market_pivots'] as $key) {
            if (! isset($snapshot[$key]) || ! is_array($snapshot[$key])) {
                throw new \DomainException("Snapshot version 3 requires [{$key}].");
            }
        }
        if ($snapshot['attributes'] === []) {
            throw new \DomainException('Snapshot attributes must contain the canonical entity row.');
        }
        foreach (['translation_records', 'section_records', 'market_ids', 'market_pivots'] as $key) {
            if (! array_is_list($snapshot[$key])) {
                throw new \DomainException("Snapshot [{$key}] must be a record list.");
            }
        }
        foreach (['translation_records', 'market_pivots'] as $key) {
            $this->assertRows($snapshot[$key], $key);
        }
        foreach ($snapshot['section_records'] as $section) {
            if (! is_array($section) || ! isset($section['attributes'], $section['translations']) || ! is_array($section['attributes']) || $section['attributes'] === [] || ! is_array($section['translations']) || ! array_is_list($section['translations'])) {
                throw new \DomainException('Snapshot sections require canonical attributes and translation records.');
            }
            $this->assertRows($section['translations'], 'section translations');
        }
        foreach ($snapshot['market_ids'] as $id) {
            if (! is_int($id) && ! is_string($id)) {
                throw new \DomainException('Snapshot market identifiers must be integers or strings.');
            }
        }
        if (array_key_exists('module_relations', $snapshot) && ! is_array($snapshot['module_relations'])) {
            throw new \DomainException('Snapshot module relations must be canonical records.');
        }
    }

    /** @param array<array-key,mixed> $rows */
    private function assertRows(array $rows, string $collection): void
    {
        foreach ($rows as $row) {
            if (! is_array($row) || $row === [] || array_is_list($row)) {
                throw new \DomainException("Snapshot [{$collection}] requires canonical rows.");
            }
        }
    }

    /** @param array<string,mixed> $snapshot */
    private function assertEntitySnapshot(Model $entity, array $snapshot): void
    {
        $this->assertValid($snapshot);
        $handler = $this->revisionHandler($entity);
        if ($handler !== null) {
            if (! isset($snapshot['module_relations']) || ! is_array($snapshot['module_relations'])) {
                throw new \DomainException('Snapshot requires canonical module relations.');
            }
            $handler->validateRevision($snapshot['module_relations']);
        }
    }

    private function revisionHandler(Model $entity): ?EntityRevisionHandlerInterface
    {
        foreach ($this->schemas->all() as $schema) {
            if ($entity instanceof $schema->model && $schema->mutationHandler !== null) {
                $handler = app($schema->mutationHandler);

                return $handler instanceof EntityRevisionHandlerInterface ? $handler : null;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR));
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

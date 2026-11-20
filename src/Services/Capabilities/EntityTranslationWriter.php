<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Content\EntitySchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Persists declared translation fields and their required unique slugs. */
class EntityTranslationWriter
{
    /**
     * @param  array<string, mixed>  $translations  locale => attributes
     */
    public function sync(EntitySchema $schema, Model $entity, array $translations, bool $creating): void
    {
        if ($translations === [] || ! $entity instanceof TranslatableEntity) {
            return;
        }

        $entity->getConnection()->transaction(function () use ($schema, $entity, $translations, $creating): void {
            $related = $entity->translations()->getRelated();
            if ($schema->uniqueSlugs && $related->isFillable('slug')) {
                // Lock the URL namespace until the enclosing mutation commits.
                $namespace = hash('sha256', $related->getTable());
                $entity->getConnection()->table('translation_slug_locks')->upsert(
                    [['namespace' => $namespace]],
                    ['namespace'],
                    ['namespace'],
                );
            }
            $this->persist($schema, $entity, $translations, $creating);
        });
    }

    /** @param array<string,mixed> $translations */
    private function persist(EntitySchema $schema, Model&TranslatableEntity $entity, array $translations, bool $creating): void
    {
        $allowed = array_values($schema->translationAttributes());
        $relation = $entity->translations();
        $related = $relation->getRelated();

        foreach ($translations as $locale => $values) {
            if (! is_string($locale) || $locale === '' || ! is_array($values)) {
                continue;
            }

            /** @var Model|null $translation */
            $translation = (clone $relation)->where('locale', $locale)
                ->when($related->isFillable('market_id'), static fn ($query) => $query->whereNull('market_id'))
                ->lockForUpdate()->first();

            $meaningful = array_intersect_key($values, array_flip(array_diff($allowed, ['schema_type'])));
            if ($translation === null && array_filter($meaningful, static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '') === []) {
                continue;
            }

            if ($translation === null) {
                $translation = $related->newInstance();
                $translation->setAttribute('locale', $locale);
            }

            $translationFillable = $translation->getFillable();

            foreach ($values as $key => $value) {
                if ($key === 'slug' || in_array($key, $allowed, true)) {
                    if (in_array($key, $translationFillable, true)) {
                        $translation->setAttribute($key, $value);
                    }
                }
            }

            if ($related->isFillable('slug')) {
                $slug = (string) $translation->getAttribute('slug');
                if ($slug === '') {
                    $title = (string) ($translation->getAttribute('title') ?? $translation->getAttribute('name') ?? '');
                    $slug = Str::slug($title) ?: $schema->type;
                }
                if ($schema->uniqueSlugs && ($creating || ! $translation->exists || $translation->isDirty('slug') || $translation->getAttribute('slug') === null || $translation->getAttribute('slug') === '')) {
                    $slug = $this->uniqueSlug($translation, $slug, $relation->getForeignKeyName(), $entity->getKey());
                }
                $translation->setAttribute('slug', $slug);
            }

            // Columns the table requires (NOT NULL without default) must be
            // satisfied even when the agent does not send them: an empty body
            // is valid content, a failed insert is not.
            foreach ($this->requiredStringColumns($translation) as $column) {
                if ($translation->getAttribute($column) === null) {
                    $translation->setAttribute($column, '');
                }
            }

            $relation->save($translation);
        }
    }

    /**
     * NOT NULL text columns of a translation table, resolved once per table.
     *
     * @return list<string>
     */
    private function requiredStringColumns(Model $translation): array
    {
        static $cache = [];

        $table = $translation->getTable();

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $columns = [];

        try {
            foreach (Schema::getColumns($table) as $column) {
                $type = (string) ($column['type_name'] ?? '');
                $isText = str_contains($type, 'char') || str_contains($type, 'text');

                if ($isText && ($column['nullable'] ?? true) === false && ($column['default'] ?? null) === null) {
                    $columns[] = (string) $column['name'];
                }
            }
        } catch (\Throwable) {
            $columns = [];
        }

        return $cache[$table] = $columns;
    }

    private function uniqueSlug(Model $translation, string $base, string $foreignKey, mixed $owner): string
    {
        $query = $translation->newQuery()
            ->where('locale', $translation->getAttribute('locale'))
            ->where($foreignKey, '!=', $owner)
            ->lockForUpdate();
        for ($attempt = 1; $attempt <= 25; $attempt++) {
            $suffix = $attempt === 1 ? '' : '-'.$attempt;
            $candidate = mb_substr($base, 0, 255 - strlen($suffix)).$suffix;
            if ((clone $query)->where('slug', $candidate)->first([$translation->getKeyName()]) === null) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['slug' => 'Elige un slug distinto para este idioma.']);
    }
}

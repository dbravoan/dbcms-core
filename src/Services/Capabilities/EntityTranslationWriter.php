<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Content\Contracts\TranslatableEntity;
use DbCms\Core\Content\EntitySchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        $columns = $schema->translationAttributes();
        $allowed = array_values($columns);

        foreach ($translations as $locale => $values) {
            if (! is_string($locale) || $locale === '' || ! is_array($values)) {
                continue;
            }

            /** @var Model|null $translation */
            $translation = $entity->translations()->where('locale', $locale)->first();

            $meaningful = array_intersect_key($values, array_flip(array_diff($allowed, ['schema_type'])));
            if ($translation === null && array_filter($meaningful, static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '') === []) {
                continue;
            }

            if ($translation === null) {
                $translation = $entity->translations()->getRelated()->newInstance();
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

            // A readable slug is required by every routing surface.
            $slug = (string) $translation->getAttribute('slug');

            if ($slug === '') {
                $title = (string) ($translation->getAttribute('title')
                    ?? $translation->getAttribute('name')
                    ?? '');

                $base = Str::slug($title) ?: $schema->type;
                $translation->setAttribute('slug', $schema->uniqueSlugs ? $this->uniqueSlug($translation, $base) : $base);
            } elseif (! $creating && $schema->uniqueSlugs) {
                $translation->setAttribute('slug', $this->uniqueSlug($translation, $slug));
            }

            // Columns the table requires (NOT NULL without default) must be
            // satisfied even when the agent does not send them: an empty body
            // is valid content, a failed insert is not.
            foreach ($this->requiredStringColumns($translation) as $column) {
                if ($translation->getAttribute($column) === null) {
                    $translation->setAttribute($column, '');
                }
            }

            $entity->translations()->save($translation);
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

    private function uniqueSlug(Model $translation, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'item';
        $candidate = $base;
        $attempt = 1;

        while ($this->slugExists($translation, $candidate) && $attempt < 25) {
            $candidate = $base.'-'.(++$attempt);
        }

        return $candidate;
    }

    private function slugExists(Model $translation, string $slug): bool
    {
        $query = $translation->newQuery()
            ->where('slug', $slug)
            ->where('locale', $translation->getAttribute('locale'));

        $key = $translation->getKey();

        if ($key !== null) {
            $query->whereKeyNot($key);
        }

        return $query->exists();
    }
}

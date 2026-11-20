<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/** Public reads stay in the requested locale; a market overrides only its global row. */
final class PublicTranslation
{
    public static function resolve(Model $entity, string $locale, ?Market $market): ?Model
    {
        return self::resolveForMarketId($entity, $locale, $market?->getKey());
    }

    /**
     * Resolve a translation without loading a Market model.
     *
     * Only the requested locale is considered: a regional row may inherit
     * blank fields from the global row of the same language, never from another
     * language or market.
     */
    public static function resolveForMarketId(Model $entity, string $locale, ?int $marketId): ?Model
    {
        $entity->loadMissing('translations');
        $global = null;
        $regional = null;
        foreach ($entity->getRelation('translations') as $translation) {
            if (! $translation instanceof Model || $translation->getAttribute('locale') !== $locale) {
                continue;
            }
            $translationMarketId = $translation->getAttributes()['market_id'] ?? null;
            if ($translationMarketId === null) {
                $global ??= $translation;
            } elseif ($marketId !== null && (int) $translationMarketId === $marketId) {
                $regional ??= $translation;
            }
        }
        if ($regional === null) {
            return $global;
        }
        $resolved = clone $regional;
        if ($global !== null) {
            foreach ($global->getAttributes() as $field => $value) {
                if (self::isBlank($regional->getAttribute($field))) {
                    $resolved->setAttribute($field, $global->getAttribute($field));
                }
            }
        }

        return $resolved;
    }

    /** Constrains the same regional/global field that resolve() returns, before pagination.
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function constrainResolvedField(Builder $query, Market $market, string $locale, string $column, string $operator, string|int $value): void
    {
        if (! in_array($column, ['title', 'name', 'slug'], true) || ! in_array($operator, ['=', 'like'], true)) {
            throw new InvalidArgumentException('Unsupported public translation constraint.');
        }
        $model = $query->getModel();
        if (! method_exists($model, 'translations') || ! ($relation = $model->translations()) instanceof HasMany) {
            throw new InvalidArgumentException('Public translation constraints require canonical translation records.');
        }
        $related = $relation->getRelated();
        $from = $query->getQuery()->from;
        $parentTable = is_string($from) && preg_match('/\s+(?:as\s+)?([a-zA-Z_][a-zA-Z0-9_]*)$/i', $from, $alias) === 1 ? $alias[1] : $model->getTable();
        $query->where(static function (QueryBuilder $translation) use ($related, $relation, $parentTable, $locale, $column, $market): void {
            $translation->from($related->getTable())->select($column)
                ->whereColumn($related->qualifyColumn($relation->getForeignKeyName()), $parentTable.'.'.$relation->getLocalKeyName())
                ->where('locale', $locale);
            if (in_array('market_id', $related->getFillable(), true)) {
                $nonblank = match ($column) {
                    'title' => "TRIM(title) <> ''",
                    'name' => "TRIM(name) <> ''",
                    'slug' => "TRIM(slug) <> ''",
                };
                $translation->where(static function (QueryBuilder $candidate) use ($market, $nonblank): void {
                    $candidate->whereNull('market_id')->orWhere(static function (QueryBuilder $regional) use ($market, $nonblank): void {
                        $regional->where('market_id', $market->id)->whereRaw($nonblank);
                    });
                })->orderByRaw('CASE WHEN market_id IS NULL THEN 1 ELSE 0 END');
            }
            $translation->orderBy($related->getKeyName())->limit(1);
        }, $operator, $value);
    }

    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim($value) === '');
    }
}

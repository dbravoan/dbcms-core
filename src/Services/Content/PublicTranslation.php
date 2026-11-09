<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Model;

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

    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim($value) === '');
    }
}

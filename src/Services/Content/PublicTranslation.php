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
        $entity->loadMissing('translations');
        $global = null;
        $regional = null;
        foreach ($entity->getRelation('translations') as $translation) {
            if (! $translation instanceof Model || $translation->getAttribute('locale') !== $locale) {
                continue;
            }
            $marketId = $translation->getAttributes()['market_id'] ?? null;
            if ($marketId === null) {
                $global ??= $translation;
            } elseif ($market !== null && (int) $marketId === (int) $market->getKey()) {
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

<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entity with one row per locale/market.
 *
 * Generic consumers (the MCP capability layer, importers, the AI assistant)
 * need to reach the translations without knowing the concrete model, so the
 * contract is explicit instead of a runtime guess.
 */
interface TranslatableEntity
{
    /**
     * Template arguments are covariant so each model may narrow them to its own
     * translation class (PostTranslation, PageTranslation…).
     *
     * @return HasMany<covariant Model, covariant Model>
     */
    public function translations(): HasMany;
}

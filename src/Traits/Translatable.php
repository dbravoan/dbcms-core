<?php

declare(strict_types=1);

namespace DbCms\Core\Traits;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

trait Translatable
{
    /**
     * @return HasMany<mixed, $this>
     */
    abstract public function translations(): HasMany;

    /**
     * Resolves the translated field value with intelligent market & locale fallback chains.
     */
    public function translate(string $field, ?string $locale = null, ?Market $market = null): mixed
    {
        $locale = $locale ?? App::getLocale();
        $translations = $this->translations;

        // 1. Direct match: locale + specific market override
        if ($market !== null) {
            $exactMarketTranslation = $translations
                ->where('locale', $locale)
                ->where('market_id', $market->id)
                ->first();

            if ($exactMarketTranslation && ! empty($exactMarketTranslation->{$field})) {
                return $exactMarketTranslation->{$field};
            }
        }

        // 2. Global match for locale (market_id IS NULL)
        $globalLocaleTranslation = $translations
            ->where('locale', $locale)
            ->where('market_id', null)
            ->first();

        if ($globalLocaleTranslation && ! empty($globalLocaleTranslation->{$field})) {
            return $globalLocaleTranslation->{$field};
        }

        // 3. Fallback to market's default locale if different
        if ($market !== null && $market->default_locale !== $locale) {
            $marketDefaultTranslation = $translations
                ->where('locale', $market->default_locale)
                ->first();

            if ($marketDefaultTranslation && ! empty($marketDefaultTranslation->{$field})) {
                return $marketDefaultTranslation->{$field};
            }
        }

        // 4. Global fallback locale
        $fallbackLocale = config('app.fallback_locale', 'en');
        $fallbackTranslation = $translations
            ->where('locale', $fallbackLocale)
            ->first();

        if ($fallbackTranslation && ! empty($fallbackTranslation->{$field})) {
            return $fallbackTranslation->{$field};
        }

        // 5. Return first non-empty translation or null
        $firstAvailable = $translations->first();

        return $firstAvailable ? $firstAvailable->{$field} : null;
    }
}

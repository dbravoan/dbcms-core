<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LocalizationService implements LocalizationServiceInterface
{
    /** @var Collection<int,Language>|null */
    protected ?Collection $activeLanguagesCache = null;

    /** @var Collection<int,Market>|null */
    protected ?Collection $activeMarketsCache = null;

    /**
     * Forget the memoized languages / markets. Called whenever the backoffice
     * writes a language or a market so the very next read is reactive (a newly
     * created language shows up in the settings screen in the same request).
     */
    public function forgetCache(): void
    {
        $this->activeLanguagesCache = null;
        $this->activeMarketsCache = null;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveMarket(Request $request): Market
    {
        $routeMarket = null;
        try {
            $routeMarket = $request->route('market');
        } catch (\Throwable) {
            // In unit tests without bound routes
        }

        if (empty($routeMarket) && $request->segment(1) && Market::where('code', $request->segment(1))->exists()) {
            $routeMarket = $request->segment(1);
        }

        $headerMarket = (string) $request->header('X-Market');
        $marketCode = ! empty($routeMarket) ? (string) $routeMarket : (! empty($headerMarket) ? $headerMarket : null);

        if ($marketCode !== null) {
            $market = Market::where('code', $marketCode)->where('is_active', true)->first();
            if ($market) {
                return $market;
            }
        }

        /** @var Market $defaultMarket */
        $defaultMarket = Market::where('is_default', true)->where('is_active', true)->first()
            ?? Market::where('is_active', true)->first()
            ?? new Market(['code' => 'es', 'name' => 'Spain Market', 'currency' => 'EUR', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);

        return $defaultMarket;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveLocale(Request $request, Market $market): Language
    {
        $routeLocale = null;
        try {
            $routeLocale = $request->route('locale');
        } catch (\Throwable) {
            // In unit tests without bound routes
        }

        // Check if segment 1 is a language code (for single-market / multi-lang routing like /en/blog)
        if (empty($routeLocale) && $request->segment(1) && Language::where('code', $request->segment(1))->where('is_active', true)->exists()) {
            $routeLocale = $request->segment(1);
        }

        if (empty($routeLocale) && $request->segment(2) && Language::where('code', $request->segment(2))->where('is_active', true)->exists()) {
            $routeLocale = $request->segment(2);
        }

        $localeCode = ! empty($routeLocale) ? (string) $routeLocale : $market->default_locale;

        $language = $market->languages()->where('code', $localeCode)->where('is_active', true)->first()
            ?? Language::where('code', $localeCode)->where('is_active', true)->first();

        if ($language) {
            return $language;
        }

        /** @var Language $defaultLang */
        $defaultLang = Language::where('is_default', true)->where('is_active', true)->first()
            ?? Language::where('is_active', true)->first()
            ?? new Language(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true]);

        return $defaultLang;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMultipleMarkets(): bool
    {
        return Market::where('is_active', true)->count() > 1;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMultipleLanguages(?Market $market = null): bool
    {
        if ($market !== null) {
            return $market->languages()->where('is_active', true)->count() > 1;
        }

        return Language::where('is_active', true)->count() > 1;
    }

    /**
     * Active languages, default first. Served from a per-request cache so the
     * dozens of backoffice surfaces that need the same list never re-query.
     *
     * @return Collection<int, Language>
     */
    public function activeLanguages(): Collection
    {
        if ($this->activeLanguagesCache !== null) {
            return $this->activeLanguagesCache;
        }

        $languages = Language::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($languages->isEmpty()) {
            $languages = collect([$this->defaultLanguage()]);
        }

        $default = $languages->firstWhere('is_default', true);

        if ($default !== null) {
            $languages = $languages
                ->reject(static fn (Language $language): bool => $language->is($default))
                ->prepend($default)
                ->values();
        }

        return $this->activeLanguagesCache = $languages;
    }

    /**
     * {@inheritdoc}
     */
    public function activeLocales(): array
    {
        /** @var list<string> $locales */
        $locales = $this->activeLanguages()
            ->map(static fn (Language $language): string => (string) $language->code)
            ->values()
            ->all();

        return $locales;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultLocale(): string
    {
        return (string) ($this->activeLanguages()->first()->code ?? 'es');
    }

    /**
     * {@inheritdoc}
     */
    public function activeMarkets(): Collection
    {
        if ($this->activeMarketsCache !== null) {
            return $this->activeMarketsCache;
        }

        $markets = Market::query()
            ->with('languages')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $default = $markets->firstWhere('is_default', true);

        if ($default !== null) {
            $markets = $markets
                ->reject(static fn (Market $market): bool => $market->is($default))
                ->prepend($default)
                ->values();
        }

        return $this->activeMarketsCache = $markets;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultMarket(): ?Market
    {
        return $this->activeMarkets()->first();
    }

    /**
     * The default language, with an unsaved display value before installation.
     */
    private function defaultLanguage(): Language
    {
        return Language::query()->where('is_default', true)->where('is_active', true)->first()
            ?? Language::query()->where('is_active', true)->first()
            ?? new Language(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true]);
    }

    /** @return array<string, string> */
    private function prefixDefaults(string $moduleKey): array
    {
        $settings = app(ModuleManager::class)->getModuleManifest($moduleKey)['settings'] ?? [];
        $defaults = $settings['slug_{locale}']['defaults'] ?? [];

        return is_array($defaults) ? array_filter($defaults, static fn (mixed $value): bool => is_string($value) && $value !== '') : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getModulePrefix(string $moduleKey, string $locale): string
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            /** @var ModuleSettingsServiceInterface $settingsService */
            $settingsService = app(ModuleSettingsServiceInterface::class);

            $customLocaleSlug = $settingsService->get($moduleKey, "slug_{$locale}");
            if (is_string($customLocaleSlug) && ! empty($customLocaleSlug)) {
                return $customLocaleSlug;
            }

            $customDefaultSlug = $settingsService->get($moduleKey, 'slug_default');
            if (is_string($customDefaultSlug) && ! empty($customDefaultSlug)) {
                return $customDefaultSlug;
            }
        }

        return $this->prefixDefaults($moduleKey)[$locale] ?? $moduleKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllModuleSlugs(string $moduleKey): array
    {
        $slugs = array_values($this->prefixDefaults($moduleKey));

        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            try {
                /** @var ModuleSettingsServiceInterface $settingsService */
                $settingsService = app(ModuleSettingsServiceInterface::class);

                $defaultVal = $settingsService->get($moduleKey, 'slug_default');
                if (is_string($defaultVal) && ! empty($defaultVal)) {
                    $slugs[] = $defaultVal;
                }

                $locales = Language::where('is_active', true)->pluck('code')->all();
                foreach ($locales as $loc) {
                    $locVal = $settingsService->get($moduleKey, "slug_{$loc}");
                    if (is_string($locVal) && ! empty($locVal)) {
                        $slugs[] = $locVal;
                    }
                }

                // Every slug the module declares is a valid route segment, even
                // for a language that is not active yet: `/es/ca/botiga` must
                // resolve (and canonicalize) instead of 404ing. The schema may
                // not expand a language after it is deactivated, so the stored
                // settings are scanned directly.
                foreach ($settingsService->getAll($moduleKey) as $key => $val) {
                    if (str_starts_with((string) $key, 'slug_') && is_string($val) && $val !== '') {
                        $slugs[] = $val;
                    }
                }

                if ($settingsService instanceof ModuleSettingsService) {
                    foreach ($settingsService->storedRaw($moduleKey) as $key => $val) {
                        if (str_starts_with((string) $key, 'slug_') && is_string($val) && $val !== '') {
                            $slugs[] = $val;
                        }
                    }
                }
            } catch (\Throwable) {
                // Table might not exist during boot
            }
        }

        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * {@inheritdoc}
     */
    public function moduleAliases(): array
    {
        $aliases = [];

        foreach (app(ModuleManager::class)->discoverModules() as $moduleKey => $manifest) {
            if (empty($manifest['is_pageable'])) {
                continue;
            }
            foreach ($this->getAllModuleSlugs($moduleKey) as $slug) {
                $slug = strtolower(trim((string) $slug));

                if ($slug === '' || isset($aliases[$slug])) {
                    continue;
                }

                $aliases[$slug] = $moduleKey;
            }
        }

        return $aliases;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHreflangCluster(string $routePattern, array $localizedSlugs, ?Model $entity = null): array
    {
        $hreflangs = [];
        $activeMarkets = Market::with(['languages' => fn ($languages) => $languages->where('is_active', true)])->where('is_active', true)->get();
        $assignedMarkets = $entity !== null && method_exists($entity, 'markets') ? $entity->getRelationValue('markets')->modelKeys() : [];

        foreach ($activeMarkets as $market) {
            if ($assignedMarkets !== [] && ! in_array($market->getKey(), $assignedMarkets, true)) {
                continue;
            }
            // Module-owned availability (for example an inactive product pivot)
            // must match the public controller, rather than just market IDs.
            if ($entity !== null && method_exists($entity, 'scopeForMarket') && ! $entity->newQuery()->whereKey($entity->getKey())->scopes(['forMarket' => [$market]])->exists()) {
                continue;
            }
            foreach ($market->languages as $lang) {
                if (! isset($localizedSlugs[$lang->code]) || $localizedSlugs[$lang->code] === '') {
                    continue;
                }

                $slug = $localizedSlugs[$lang->code];
                $tag = strtolower($lang->code).'-'.strtoupper($market->code);

                // Build localized URL for this market & language pair
                $localizedRoute = str_replace(['{locale}', '{slug}'], [$lang->code, $slug], $routePattern);
                $localizedRoute = preg_replace_callback('/\{([a-zA-Z0-9_-]+)Prefix\}/', fn (array $matches): string => $this->getModulePrefix($matches[1], $lang->code), $localizedRoute) ?? $localizedRoute;

                $targetUrl = $this->url($localizedRoute, $market, $lang);
                $hreflangs[$tag] = $targetUrl;
            }
        }

        if (! empty($hreflangs)) {
            $first = (string) reset($hreflangs);
            $hreflangs['x-default'] = $hreflangs['es-ES'] ?? $first;
        }

        return $hreflangs;
    }

    /**
     * {@inheritdoc}
     */
    public function frontUrl(string $moduleKey, string $path = '', ?string $locale = null, ?Market $market = null): string
    {
        $market = $market ?? Market::where('is_default', true)->first() ?? Market::first();

        $locale = $locale ?: (string) ($market->default_locale ?? 'es');

        $prefix = $this->getModulePrefix($moduleKey, $locale);
        $path = trim($path, '/');

        return $this->url($path === '' ? "/{$prefix}" : "/{$prefix}/{$path}", $market, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function url(string $path, ?Market $market = null, Language|string|null $language = null): string
    {
        $hasMarkets = $this->hasMultipleMarkets();
        $hasLanguages = $this->hasMultipleLanguages($market);

        $marketCode = $market->code ?? 'es';
        $activeLocale = app()->getLocale();
        $localeCode = $language instanceof Language
            ? $language->code
            : (is_string($language) && ! empty($language)
                ? $language
                : ($activeLocale ?: ($market->default_locale ?? 'es')));

        $cleanPath = trim($path, '/');

        // Strip any existing market/locale prefix if path was already prefixed
        if ($cleanPath !== '') {
            $segments = explode('/', $cleanPath);
            if (count($segments) >= 2) {
                $allMarketCodes = Market::pluck('code')->all();
                if (in_array($segments[0], $allMarketCodes, true)) {
                    array_shift($segments); // Remove existing market prefix
                    array_shift($segments); // Remove existing locale prefix
                    $cleanPath = implode('/', $segments);
                }
            }
        }

        // Case 1: 1 Market & 1 Language -> Direct clean URL without any prefixes
        if (! $hasMarkets) {
            if (! $hasLanguages) {
                return url($cleanPath ?: '/');
            }

            // Case 2: 1 Market & Multiple Languages -> Language prefix only (/en/blog or /en)
            return $cleanPath !== ''
                ? url("/{$localeCode}/{$cleanPath}")
                : url("/{$localeCode}");
        }

        // Case 3: Multiple Markets -> Full market & language prefix (/es/es/blog or /es/es)
        return $cleanPath !== ''
            ? url("/{$marketCode}/{$localeCode}/{$cleanPath}")
            : url("/{$marketCode}/{$localeCode}");
    }

    /**
     * {@inheritdoc}
     */
    public function buildAlternateUrlsMap(Request $request, ?array $localizedSlugs = null): array
    {
        return $this->buildAlternateUrlsMapForPath(array_values(array_filter($request->segments(), 'is_string')), $localizedSlugs);
    }

    public function buildAlternateUrlsMapForPath(array $segments, ?array $localizedSlugs = null): array
    {
        $activeMarkets = Market::with('languages')->where('is_active', true)->get();
        $alternateUrls = [];

        // Determine current relative path without market/locale prefix
        $marketCodes = $activeMarkets->pluck('code')->all();
        $languageCodes = Language::where('is_active', true)->pluck('code')->all();

        // Strip market segment if present
        if (! empty($segments) && in_array($segments[0], $marketCodes, true)) {
            array_shift($segments);
        }

        // Strip locale segment if present
        if (! empty($segments) && in_array($segments[0], $languageCodes, true)) {
            array_shift($segments);
        }

        $basePath = implode('/', $segments);

        foreach ($activeMarkets as $market) {
            $alternateUrls[$market->code] = [];
            foreach ($market->languages as $lang) {
                if ($localizedSlugs !== null && isset($localizedSlugs[$lang->code])) {
                    // Replace slug in path with language-specific slug
                    $slugVal = (string) $localizedSlugs[$lang->code];
                    if ($slugVal === 'home' || $slugVal === 'inicio') {
                        $targetPath = '';
                    } else {
                        $module = $this->moduleAliases()[strtolower($segments[0] ?? '')] ?? null;
                        $targetPath = $module !== null
                            ? $this->getModulePrefix($module, (string) $lang->code).'/'.ltrim($slugVal, '/')
                            : ltrim($slugVal, '/');
                    }
                    $alternateUrls[$market->code][$lang->code] = $this->url((string) $targetPath, $market, $lang);
                } else {
                    $targetPath = ($basePath === 'home' || $basePath === 'inicio') ? '' : $basePath;
                    $alternateUrls[$market->code][$lang->code] = $this->url((string) $targetPath, $market, $lang);
                }
            }
        }

        return $alternateUrls;
    }
}

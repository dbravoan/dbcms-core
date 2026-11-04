<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface LocalizationServiceInterface
{
    /**
     * Resolves the active market from the request (URL prefix, domain, header, or default).
     */
    public function resolveMarket(Request $request): Market;

    /**
     * Resolves the active locale/language for the given market.
     */
    public function resolveLocale(Request $request, Market $market): Language;

    /**
     * Determines if multiple active markets exist.
     */
    public function hasMultipleMarkets(): bool;

    /**
     * Determines if multiple active languages exist (globally or within the given market).
     */
    public function hasMultipleLanguages(?Market $market = null): bool;

    /**
     * Every active language, default first. The single reactive source every
     * backoffice surface reads (never a hardcoded list), so a language created
     * in the backoffice shows up in tabs, settings, forms and menus.
     *
     * @return Collection<int, Language>
     */
    public function activeLanguages(): Collection;

    /**
     * Active locale codes, default first.
     *
     * @return list<string>
     */
    public function activeLocales(): array;

    /**
     * The default (public) language code, always one of the active ones.
     */
    public function defaultLocale(): string;

    /**
     * Every active market. Same contract as {@see activeLanguages()}.
     *
     * @return Collection<int, Market>
     */
    public function activeMarkets(): Collection;

    /**
     * The default active market.
     */
    public function defaultMarket(): ?Market;

    /**
     * Resolves localized route slug prefix for a module.
     */
    public function getModulePrefix(string $moduleKey, string $locale): string;

    /**
     * Resolves all active configured slugs for a module across all languages.
     *
     * @return array<int, string>
     */
    public function getAllModuleSlugs(string $moduleKey): array;

    /**
     * Map of every accepted module prefix alias to its module key.
     *
     * Used to consolidate non-canonical module prefixes with a 301
     * (`/tienda` is canonical in Spanish, `/shop` is an alias).
     *
     * @return array<string, string> alias slug => module key
     */
    public function moduleAliases(): array;

    /**
     * Builds bi-directional hreflang cluster for a given route and translatable entity.
     *
     * @param  array<string, string>  $localizedSlugs  Keyed by locale code => slug
     * @return array<string, string> Keyed by hreflang tag (e.g. 'es-ES', 'en-US') => full URL
     */
    public function buildHreflangCluster(string $routePattern, array $localizedSlugs, ?Model $entity = null): array;

    /**
     * Generates a smart localized URL (omits market prefix if single market, omits locale if single language).
     */
    public function url(string $path, ?Market $market = null, Language|string|null $language = null): string;

    /**
     * Front-office URL of a module entity, always using the canonical localized
     * prefix and the default market/language (`/es/es/tienda/servidor-edge`).
     *
     * Used by the backoffice "view live" links so they never hardcode a prefix
     * or emit a deprecated alias.
     */
    public function frontUrl(string $moduleKey, string $path = '', ?string $locale = null, ?Market $market = null): string;

    /**
     * Generates a complete matrix of alternate URLs for the current request across all active markets & languages.
     *
     * @param  array<string, string>|null  $localizedSlugs  Optional localized slug overrides (e.g. ['es' => 'post-es', 'en' => 'post-en'])
     * @return array<string, array<string, string>> Array keyed by [market_code][locale_code] => target_url
     */
    public function buildAlternateUrlsMap(Request $request, ?array $localizedSlugs = null): array;

    /** @param list<string> $segments
     * @param  array<string,string>|null  $localizedSlugs
     * @return array<string,array<string,string>>
     */
    public function buildAlternateUrlsMapForPath(array $segments, ?array $localizedSlugs = null): array;
}

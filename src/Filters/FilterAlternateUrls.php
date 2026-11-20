<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Models\Market;

/**
 * Builds the hreflang cluster of a dynamic filter page.
 *
 * The same filters are expressed in every active locale of the market: markers
 * **and** term values are translated, and the module prefix is localized too, so
 * each alternate points at a genuinely localized URL instead of a copy of the
 * source one. `x-default` points at the market's default locale.
 */
final class FilterAlternateUrls
{
    public function __construct(
        private readonly FilterUrlBuilder $builder,
    ) {}

    /**
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<int, array{hreflang: string, href: string}>
     */
    public function build(
        FilterSet $set,
        array $dimensions,
        FilterableModule $module,
        Market $market,
        LocalizationServiceInterface $localization,
    ): array {
        $alternates = [];
        $defaultLocale = (string) ($market->default_locale ?? $set->locale);

        foreach ($market->languages as $language) {
            if (! $language->is_active) {
                continue;
            }

            $locale = (string) $language->code;

            $href = $this->hrefFor($set, $dimensions, $module, $market, $localization, $locale);

            if ($href === null) {
                continue;
            }

            $alternates[] = ['hreflang' => $locale, 'href' => $href];

            if ($locale === $defaultLocale) {
                $alternates[] = ['hreflang' => 'x-default', 'href' => $href];
            }
        }

        return $alternates;
    }

    /**
     * @param  array<int, FilterDimension>  $dimensions
     */
    private function hrefFor(
        FilterSet $set,
        array $dimensions,
        FilterableModule $module,
        Market $market,
        LocalizationServiceInterface $localization,
        string $locale,
    ): ?string {
        $path = $this->builder->translatedPath($set, $dimensions, $set->locale, $locale, $market);

        if ($path === null || $path === '') {
            return null;
        }

        $prefix = $localization->getModulePrefix($module->filterModuleKey(), $locale);

        return $localization->url("/{$prefix}/{$path}", $market, $locale);
    }
}

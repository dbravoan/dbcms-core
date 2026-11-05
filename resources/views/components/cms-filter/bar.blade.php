@props([
    'module' => null,
    'set' => null,
    'market' => null,
    'limit' => 10,
    'variant' => null,
])

@php
    use DbCms\Core\Filters\FilterFacets;
    use DbCms\Core\Filters\FilterPanelSkin;
    use DbCms\Core\Services\Cms\FrontViewResolver;

    /** @var FrontViewResolver $viewResolver */
    $viewResolver = app(FrontViewResolver::class);

    $panel = null;

    if ($module instanceof \DbCms\Core\Filters\Contracts\FilterableModule
        && $set instanceof \DbCms\Core\Filters\FilterSet
        && $market) {
        /** @var \DbCms\Core\Contracts\Services\LocalizationServiceInterface $localization */
        $localization = app(\DbCms\Core\Contracts\Services\LocalizationServiceInterface::class);
        /** @var FilterFacets $facets */
        $facets = app(FilterFacets::class);
        $dimensions = $module->filterDimensions();

        $chips = $facets->activeChips($module, $set, $dimensions, $market, $localization);

        $panel = [
            'action' => $facets->action($module, $set, $market, $localization),
            'clearUrl' => $facets->clearAllUrl($module, $set, $market, $localization),
            'chips' => $chips,
            'activeCount' => count($chips),
            'groups' => $facets->groups($module, $set, $dimensions, $market, $localization, (int) $limit),
            'hidden' => $facets->hiddenInputs($module, $set),
        ];
    }

    $theme = $viewResolver->theme();
    /** @var array<string, mixed> $ui */
    $ui = $panel !== null ? app(FilterPanelSkin::class)->forTheme($theme) : [];
@endphp

@if($panel !== null && $panel['groups'] !== [])
    @include($viewResolver->filterPanel(), [
        'panel' => $panel,
        'ui' => $ui,
        'theme' => $theme,
        'module' => $module,
        'set' => $set,
        'market' => $market,
    ])
@endif

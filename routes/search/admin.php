<?php

declare(strict_types=1);

use DbCms\Core\Http\Middleware\CheckPermission;
use DbCms\Core\Search\Http\Controllers\Admin\SearchSettingsPostController;
use Illuminate\Support\Facades\Route;

/*
| Filtros y búsquedas → tarjeta "Búsqueda general".
|
| The activation of the search surfaces (indexability, results per page,
| analytics) lives in the very same inbox as the filter landings, so editors
| have a single place to decide what Google should index.
*/
Route::middleware([CheckPermission::class.':manage:seo'])
    ->prefix('seo/filters/search')
    ->name('seo.filters.search.')
    ->group(function (): void {
        Route::post('/', SearchSettingsPostController::class)->name('update');
    });

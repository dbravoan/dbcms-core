<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Http\Controllers\Admin\PageCreateGetController;
use DbCms\Core\Content\Pages\Http\Controllers\Admin\PageDeleteController;
use DbCms\Core\Content\Pages\Http\Controllers\Admin\PageEditGetController;
use DbCms\Core\Content\Pages\Http\Controllers\Admin\PagePostController;
use DbCms\Core\Content\Pages\Http\Controllers\Admin\PagePutController;
use DbCms\Core\Content\Pages\Http\Controllers\Admin\PagesGetController;
use DbCms\Core\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;

Route::middleware([CheckPermission::class.':manage:pages'])->prefix('pages')->name('pages.')->group(function (): void {
    Route::get('/', PagesGetController::class)->name('index');
    Route::get('/create', PageCreateGetController::class)->name('create');
    Route::post('/', PagePostController::class)->name('store');
    Route::get('/{page}/edit', PageEditGetController::class)->name('edit');
    Route::put('/{page}', PagePutController::class)->name('update');
    Route::delete('/{page}', PageDeleteController::class)->name('destroy');
});

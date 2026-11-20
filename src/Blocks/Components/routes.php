<?php

declare(strict_types=1);
use DbCms\Core\Blocks\Components\Http\ComponentCreateGetController;
use DbCms\Core\Blocks\Components\Http\ComponentDeleteController;
use DbCms\Core\Blocks\Components\Http\ComponentEditGetController;
use DbCms\Core\Blocks\Components\Http\ComponentPostController;
use DbCms\Core\Blocks\Components\Http\ComponentPreviewGetController;
use DbCms\Core\Blocks\Components\Http\ComponentPutController;
use DbCms\Core\Blocks\Components\Http\ComponentsGetController;
use Illuminate\Support\Facades\Route;

Route::prefix('block-components')->name('block-components.')->group(function (): void {
    Route::get('/', ComponentsGetController::class)->name('index');
    Route::get('/create', ComponentCreateGetController::class)->name('create');
    Route::post('/', ComponentPostController::class)->name('store');
    Route::get('/{component}/preview', ComponentPreviewGetController::class)->name('preview');
    Route::get('/{component}/edit', ComponentEditGetController::class)->name('edit');
    Route::put('/{component}', ComponentPutController::class)->name('update');
    Route::delete('/{component}', ComponentDeleteController::class)->name('destroy');
});

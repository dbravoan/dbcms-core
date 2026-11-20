<?php

declare(strict_types=1);

use DbCms\Core\Http\Middleware\CheckPermission;
use DbCms\Core\Maintenance\Http\Controllers\MaintenancePreviewGetController;
use DbCms\Core\Maintenance\Http\Controllers\MaintenanceSavePostController;
use DbCms\Core\Maintenance\Http\Controllers\MaintenanceTogglePostController;
use Illuminate\Support\Facades\Route;

Route::middleware([CheckPermission::class.':manage:modules'])->prefix('maintenance')->name('maintenance.')->group(function (): void {
    Route::post('/save', MaintenanceSavePostController::class)->name('save');
    Route::post('/toggle', MaintenanceTogglePostController::class)->name('toggle');
    Route::get('/preview', MaintenancePreviewGetController::class)->name('preview');
});

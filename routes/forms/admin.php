<?php

declare(strict_types=1);

use DbCms\Core\Forms\Http\Controllers\Admin\FormAttachmentGetController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormCreateGetController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormDeleteController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormEditGetController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormPostController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormPutController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormsGetController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormSubmissionDeleteController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormSubmissionsGetController;
use DbCms\Core\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;

Route::middleware([CheckPermission::class.':manage:forms'])->prefix('forms')->name('forms.')->group(function (): void {
    Route::get('/', FormsGetController::class)->name('index');
    Route::get('/create', FormCreateGetController::class)->name('create');
    Route::post('/', FormPostController::class)->name('store');
    Route::get('/{form}/edit', FormEditGetController::class)->name('edit');
    Route::put('/{form}', FormPutController::class)->name('update');
    Route::delete('/{form}', FormDeleteController::class)->name('destroy');
    Route::get('/attachments/{attachment}', FormAttachmentGetController::class)->name('attachments.show');
    Route::get('/submissions', FormSubmissionsGetController::class)->name('submissions');
    Route::delete('/submissions/{submission}', FormSubmissionDeleteController::class)->name('submissions.destroy');
});

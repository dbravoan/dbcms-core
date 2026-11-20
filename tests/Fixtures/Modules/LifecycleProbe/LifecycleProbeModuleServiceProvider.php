<?php

declare(strict_types=1);

namespace DbCms\Modules\LifecycleProbe;

use DbCms\Core\Modules\BaseModuleServiceProvider;
use Illuminate\Support\Facades\Route;

final class LifecycleProbeModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'lifecycle-probe';

    protected string $name = 'Lifecycle Probe';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        Route::get('/admin/module-lifecycle-probe', static fn (): string => 'Extension available')
            ->middleware(['web', 'module.enabled:lifecycle-probe']);
    }
}

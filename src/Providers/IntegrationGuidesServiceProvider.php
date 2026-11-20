<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Contracts\Services\IntegrationGuideServiceInterface;
use DbCms\Core\Integrations\IntegrationGuideRegistry;
use DbCms\Core\Services\Integrations\IntegrationGuideService;
use Illuminate\Support\ServiceProvider;

final class IntegrationGuidesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationGuideRegistry::class);
        $this->app->bind(IntegrationGuideServiceInterface::class, IntegrationGuideService::class);
    }
}

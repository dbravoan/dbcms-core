<?php

declare(strict_types=1);

namespace DbCms\Core\Mcp;

use DbCms\Core\Cms;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Mcp\Services\McpToolDispatcher;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(McpToolDispatcher::class);
    }

    public function boot(): void
    {
        $views = Cms::path('resources/views/mcp');
        $this->loadViewsFrom($views, 'mcp');
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('mcp', $views);

        RateLimiter::for('mcp', static function (Request $request): Limit {
            // Authentication runs first: identity never comes from the RPC payload.
            $identity = $request->attributes->get('is_m2m')
                ? 'client:'.$request->attributes->get('oauth_client_id')
                : 'user:'.$request->user()?->getAuthIdentifier();
            $perMinute = max(1, (int) app(CoreFeatureSettings::class)->get('mcp', 'rate_limit_per_minute', 60));

            return Limit::perMinute($perMinute)->by('mcp:'.$identity);
        });
        $this->loadRoutesFrom(Cms::path('routes/mcp/native.php'));
    }
}

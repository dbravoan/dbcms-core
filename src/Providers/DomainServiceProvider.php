<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Contracts\Services\GeoServiceInterface;
use DbCms\Core\Contracts\Services\SeoServiceInterface;
use DbCms\Core\Contracts\Services\UserAuthServiceInterface;
use DbCms\Core\Services\Auth\UserAuthService;
use DbCms\Core\Services\Geo\LlmsTxtGeneratorService;
use DbCms\Core\Services\Seo\SitemapService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserAuthServiceInterface::class => UserAuthService::class,
        GeoServiceInterface::class => LlmsTxtGeneratorService::class,
        SeoServiceInterface::class => SitemapService::class,
    ];
}

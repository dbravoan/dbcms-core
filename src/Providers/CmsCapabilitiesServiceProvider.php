<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\CapabilityAuditInterface;
use DbCms\Core\Capabilities\Contracts\CapabilityExecutorInterface;
use DbCms\Core\Capabilities\Contracts\CapabilityPresentationInterface;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Capabilities\Tools\BlockPresetsTool;
use DbCms\Core\Capabilities\Tools\BlockReferencesTool;
use DbCms\Core\Capabilities\Tools\BlockTypesTool;
use DbCms\Core\Contracts\Services\ExecutionServiceInterface;
use DbCms\Core\Services\Capabilities\CapabilityAuditLogger;
use DbCms\Core\Services\Capabilities\CapabilityAuditService;
use DbCms\Core\Services\Capabilities\CmsActionCatalog;
use DbCms\Core\Services\Capabilities\CmsCapabilityPresentation;
use DbCms\Core\Services\Capabilities\DiscoveryCapabilities;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Capabilities\PlatformResourceCatalog;
use DbCms\Core\Services\Capabilities\ResourceCrudService;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Support\ServiceProvider;

/** Registers CMS domain adapters even if the MCP transport is disabled. */
final class CmsCapabilitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExecutionServiceInterface::class, ExecutionService::class);
        $this->app->bind(CapabilityExecutorInterface::class, CapabilityExecutor::class);
        $this->app->bind(CapabilityPresentationInterface::class, CmsCapabilityPresentation::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->scoped(ExecutionContext::class);
        $this->app->singleton(CapabilityResourceRegistry::class);
        $this->app->singleton(EntityMutationService::class);
        $this->app->singleton(EntityReadService::class);
        $this->app->singleton(ResourceCrudService::class);
        $this->app->singleton(CapabilityAuditLogger::class);
        $this->app->bind(CapabilityAuditInterface::class, CapabilityAuditService::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $resources = $this->app->make(CapabilityResourceRegistry::class);
            $resources->registerMany(PlatformResourceCatalog::all());
            $registry = $this->app->make(CapabilityRegistry::class);
            $registry->register($this->app->make(BlockTypesTool::class), effect: 'read');
            $registry->register($this->app->make(BlockReferencesTool::class), effect: 'read');
            $registry->register($this->app->make(BlockPresetsTool::class), effect: 'read');
            foreach (['EntityTypes', 'EntityList', 'EntityGet', 'EntityCreate', 'EntityUpdate', 'EntityDelete'] as $name) {
                $registry->register($this->app->make('DbCms\\Core\\Capabilities\\Tools\\Entity\\'.$name.'Tool'));
            }
            foreach (['ResourceTypes', 'ResourceList', 'ResourceGet', 'ResourceCreate', 'ResourceUpdate', 'ResourceDelete'] as $name) {
                $registry->register($this->app->make('DbCms\\Core\\Capabilities\\Tools\\Resource\\'.$name.'Tool'));
            }
            foreach ($this->app->make(CmsActionCatalog::class)->all() as $tool) {
                $effect = match ($tool->name()) {
                    'cms_content_sections_sync', 'cms_content_sections_patch', 'cms_notifications_mark_all_read', 'cms_media_upload', 'cms_cache_purge', 'cms_seo_filter_upsert' => 'draft',
                    default => $tool->destructive() ? 'delete' : ($tool->mutating() ? 'external' : 'read'),
                };
                $registry->register($tool, effect: $effect);
            }
            $this->app->make(DiscoveryCapabilities::class)->register($registry);
        });
    }
}

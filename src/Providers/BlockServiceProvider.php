<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Blocks\BlockBindings;
use DbCms\Core\Blocks\BlockEditorCatalogue;
use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\BlockRegistry;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\BlockSources;
use DbCms\Core\Blocks\BlockStorageDisclosure;
use DbCms\Core\Blocks\CollectionBlock;
use DbCms\Core\Blocks\Components\BlockComponentsServiceProvider;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use DbCms\Core\Events\ModuleAvailabilityChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockPackageManager::class);
        $this->app->singleton(BlockStorageDisclosure::class);
        $this->app->tag(BlockStorageDisclosure::class, 'cms.cookie-disclosures');
        $this->app->singleton(BlockRegistryInterface::class, BlockRegistry::class);
        $this->app->scoped(BlockRenderer::class);
        $this->app->scoped(BlockSources::class);
        $this->app->scoped(BlockBindings::class);
        $this->app->register(BlockComponentsServiceProvider::class);

    }

    public function boot(): void
    {
        Event::listen([
            ModuleAvailabilityChanged::class,
            BlockPackageAvailabilityChanged::class,
        ], function (): void {
            $registry = $this->app->make(BlockRegistryInterface::class);
            if ($registry instanceof BlockRegistry) {
                $registry->invalidateAvailability();
            }
        });
        \Illuminate\Support\Facades\View::composer('components.admin.block-manager', function (View $view): void {
            $view->with($this->app->make(BlockEditorCatalogue::class)->data());
        });
        $registry = $this->app->make(BlockRegistryInterface::class);
        $registry->register($this->app->make(CollectionBlock::class));
        foreach ($this->app->make(BlockPackageManager::class)->all() as $id => $package) {
            if (is_dir($package['directory'].'/Resources/views')) {
                $this->loadViewsFrom($package['directory'].'/Resources/views', $id);
            }
            foreach ($package['blocks'] as $class) {
                $block = $this->app->make($class);
                if (! $block instanceof BlockTypeInterface) {
                    throw new \DomainException('Package definitions must implement BlockTypeInterface.');
                }
                $registry->register($block, $id);
            }
        }
    }
}

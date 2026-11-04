<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Blocks\BlockEditorCatalogue;
use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\BlockRegistry;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockPackageManager::class);
        $this->app->singleton(BlockRegistryInterface::class, BlockRegistry::class);

    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('components.admin.block-manager', function (View $view): void {
            $view->with($this->app->make(BlockEditorCatalogue::class)->data());
        });
        $registry = $this->app->make(BlockRegistryInterface::class);
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

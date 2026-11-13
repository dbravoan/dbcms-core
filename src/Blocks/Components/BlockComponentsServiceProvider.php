<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components;

use DbCms\Core\Blocks\Components\Services\ComponentRenderer;
use DbCms\Core\Blocks\Components\Services\ComponentSearchInvalidator;
use DbCms\Core\Blocks\Components\Services\ComponentSearchObserver;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Http\Middleware\CheckPermission;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\BlockComponentSection;
use DbCms\Core\Models\BlockComponentSectionTranslation;
use DbCms\Core\Models\BlockComponentTranslation;
use DbCms\Core\Observers\ContentTranslationCacheObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BlockComponentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ComponentRenderer::class);
        $this->app->scoped(ComponentSearchInvalidator::class);
        config(['cms.page_cache_models' => array_values(array_unique(array_merge((array) config('cms.page_cache_models', []), [BlockComponent::class])))]);
    }

    public function boot(): void
    {
        $this->app->make(EntitySchemaRegistryInterface::class)->register(BlockComponentSchema::definition());
        $this->app->make(BlockRegistryInterface::class)->register($this->app->make(ComponentBlock::class));
        foreach ([BlockComponent::class, BlockComponentTranslation::class, BlockComponentSection::class, BlockComponentSectionTranslation::class] as $model) {
            $model::observe(ComponentSearchObserver::class);
        }
        BlockComponentTranslation::observe(ContentTranslationCacheObserver::class);
        BlockComponentSectionTranslation::observe(ContentTranslationCacheObserver::class);
        Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
            ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class, CheckPermission::class.':manage:pages'])->group(__DIR__.'/routes.php');
    }
}

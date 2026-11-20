<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\NativeBlocks;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('provides every native primitive without activating a block package or reading the database', function (): void {
    DB::connection()->beforeExecuting(static function (): void {
        throw new LogicException('Native block discovery must not query data.');
    });
    $registry = app(BlockRegistryInterface::class);
    expect(NativeBlocks::TYPES)->toHaveCount(28);
    foreach (NativeBlocks::TYPES as $class) {
        $definition = app($class);
        expect($registry->get($definition->getType()))->toBeInstanceOf($class)
            ->and($registry->isAvailable($definition->getType()))->toBeTrue()
            ->and($definition->getRequiredModule())->toBeNull();
    }
    expect($registry->all())->toHaveKeys(['rich_text', 'accordion', 'charts', 'kpi', 'roi']);
});

it('provides the default administrative appearance entirely from core', function (): void {
    $themes = app(AdminThemeServiceInterface::class);
    $manifest = $themes->getThemeManifest('dbcms');
    expect($themes->themeExists('dbcms'))->toBeTrue()
        ->and($themes->getThemePath('dbcms'))->toEndWith('/resources/admin-theme')
        ->and($manifest['version'])->toBe(InstalledVersions::getPrettyVersion('dbravoan/dbcms-core') ?? 'dev-main')
        ->and(is_file($themes->getThemePath('dbcms').'/assets/admin-bar.css'))->toBeTrue()
        ->and(InstalledVersions::isInstalled('dbravoan/dbcms-admin-theme-dbcms'))->toBeFalse()
        ->and(cms_asset_entries())->toContain('vendor/dbravoan/dbcms-core/resources/admin-theme/theme.css');
});

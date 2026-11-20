<?php

declare(strict_types=1);

use Acme\Visuals\StatisticBlock;
use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\BlockRegistry;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Providers\BlockServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\Support\ComposerPackageInventory;

require_once __DIR__.'/../../Fixtures/Blocks/StatisticBlock.php';

test('Composer visual discovery stays read only and third-party definitions execute only after activation', function (): void {
    $inventory = new ComposerPackageInventory;
    $inventory->add('acme/statistics', 'dbcms-block-package', [
        'identifier' => 'acme-statistics', 'name' => 'Statistics', 'blocks' => [StatisticBlock::class],
    ], ['dbravoan/dbcms-core' => '~0.0.1']);
    try {
        app()->instance(ComposerPackages::class, new ComposerPackages);
        $manager = new BlockPackageManager(app(ComposerPackages::class));
        app()->instance(BlockPackageManager::class, $manager);
        $registry = new BlockRegistry(app(ModuleManager::class), $manager);
        app()->instance(BlockRegistryInterface::class, $registry);
        StatisticBlock::$renders = 0;
        (new BlockServiceProvider(app()))->boot();

        expect($manager->all()['acme-statistics']['package'])->toBe('acme/statistics')
            ->and(DB::table('block_packages')->where('identifier', 'acme-statistics')->exists())->toBeFalse()
            ->and($registry->get('acme-statistic'))->toBeInstanceOf(StatisticBlock::class)
            ->and($registry->getActiveBlocks())->not->toHaveKey('acme-statistic')
            ->and($registry->render('acme-statistic', []))->toBe('')
            ->and(StatisticBlock::$renders)->toBe(0);

        $manager->setEnabled('acme-statistics', true);
        expect($registry->render('acme-statistic', []))->toBe('Third-party statistic');
        $manager->setEnabled('acme-statistics', false);
        expect($registry->render('acme-statistic', []))->toBe('')
            ->and(StatisticBlock::$renders)->toBe(1)
            ->and($registry->get('acme-statistic'))->toBeInstanceOf(StatisticBlock::class);
    } finally {
        $inventory->restore();
    }
});

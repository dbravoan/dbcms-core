<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$appPath = $argv[1];
$package = $argv[2];
$identifier = $argv[3];
$probe = json_decode((string) file_get_contents($argv[4]), true, flags: JSON_THROW_ON_ERROR);
putenv('CMS_MODULES=');
$_ENV['CMS_MODULES'] = $_SERVER['CMS_MODULES'] = '';
require $appPath.'/vendor/autoload.php';
$app = require $appPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$installedPath = InstalledVersions::getInstallPath($package);
if (! is_string($installedPath) || is_link($installedPath) || ! str_starts_with((string) realpath($installedPath), $appPath.'/vendor/')) {
    throw new RuntimeException('Candidate must be a dist installation inside the isolated application vendor.');
}
$expectedDatabase = (string) getenv('DBCMS_TEST_DATABASE');
if (getenv('DBCMS_TEST_ISOLATED') !== '1'
    || preg_match('/^(?:core_testing|core_test_[a-f0-9]{12}|dbcms_test_[a-f0-9]{12})$/D', $expectedDatabase) !== 1
    || ! $app->environment('testing')
    || DB::connection()->getDatabaseName() !== $expectedDatabase
    || DB::selectOne('SELECT DATABASE() AS name')->name !== $expectedDatabase) {
    throw new RuntimeException('Distribution smoke requires an explicitly selected disposable MySQL database.');
}
if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0) {
    throw new RuntimeException('Disposable database migration failed.');
}

switch ($probe['kind']) {
    case 'module':
        moduleSmoke($app, $identifier, $probe);
        break;
    case 'block-package':
        $manager = $app->make(BlockPackageManager::class);
        $before = DB::table('block_packages')->count();
        if (! isset($manager->all()[$identifier]) || $manager->isEnabled($identifier)
            || DB::table('block_packages')->count() !== $before) {
            throw new RuntimeException('Block package discovery must be read-only and inactive.');
        }
        $tables = Schema::getTableListing(DB::connection()->getDatabaseName());
        $manager->setEnabled($identifier, true);
        $registry = $app->make(BlockRegistryInterface::class);
        foreach ($probe['blockTypes'] as $type) {
            if ($registry->get($type) === null || ! $registry->isAvailable($type)) {
                throw new RuntimeException('Enabled block was not registered and available: '.$type);
            }
        }
        $manager->setEnabled($identifier, false);
        foreach ($probe['blockTypes'] as $type) {
            if ($registry->isAvailable($type)) {
                throw new RuntimeException('Disabled block remains available: '.$type);
            }
        }
        $manager->setEnabled($identifier, true);
        if (! $manager->isEnabled($identifier) || Schema::getTableListing(DB::connection()->getDatabaseName()) !== $tables) {
            throw new RuntimeException('Block activation changed schema or did not restore availability.');
        }
        break;
    case 'theme':
        $manager = $app->make(ThemeManagerService::class);
        if (! isset($manager->getAvailableThemes()[$identifier])
            || ! str_starts_with((string) realpath($manager->path($identifier)), (string) realpath($installedPath))) {
            throw new RuntimeException('Public theme did not resolve from its installed distribution.');
        }
        if (! $manager->setActiveTheme($identifier) || $manager->getActiveTheme() !== $identifier) {
            throw new RuntimeException('Public theme selection failed.');
        }
        break;
    case 'admin-theme':
        $manager = $app->make(AdminThemeServiceInterface::class);
        if (! $manager->themeExists($identifier)
            || ! str_starts_with((string) realpath($manager->getThemePath($identifier)), (string) realpath($installedPath))) {
            throw new RuntimeException('Admin theme did not resolve from its installed distribution.');
        }
        $manager->setActiveTheme($identifier);
        if ($manager->getActiveTheme() !== $identifier) {
            throw new RuntimeException('Admin theme selection failed.');
        }
        break;
    case 'core':
        if (! Route::has('install.step1') || ! Route::has('admin.login')
            || ! $app->make(AdminThemeServiceInterface::class)->themeExists('dbcms')) {
            throw new RuntimeException('Core distribution lacks its installer, admin routes or native admin theme.');
        }
        $registry = $app->make(BlockRegistryInterface::class);
        foreach ($probe['blockTypes'] as $type) {
            if ($registry->get($type) === null || ! $registry->isAvailable($type)) {
                throw new RuntimeException('Native block is missing or requires external activation: '.$type);
            }
        }
        break;
    default:
        throw new RuntimeException('Unsupported distribution kind.');
}

if (is_string($probe['probe']) && $probe['probe'] !== '') {
    // A repository-owned probe may exercise behavior of the installed candidate;
    // it never contributes autoload or runtime code to the installed package.
    require dirname(__DIR__).'/'.$probe['probe'];
}
fwrite(STDOUT, 'Distribution smoke passed for '.$probe['kind'].': '.$package."\n");

/** @param array<string, mixed> $probe */
function moduleSmoke(Application $app, string $identifier, array $probe): void
{
    $manager = $app->make(ModuleManager::class);
    $before = DB::table('cms_modules')->count();
    if (! $manager->isModuleDiscovered($identifier) || $manager->isModuleEnabled($identifier)
        || DB::table('cms_modules')->count() !== $before) {
        throw new RuntimeException('Discovery must be available, inactive and read-only.');
    }
    config(['cms.modules' => $probe['modules']]);
    $manager->installConfiguredModules();
    if (! $manager->isModuleEnabled($identifier)) {
        throw new RuntimeException('Explicit installation did not activate the module.');
    }
    foreach ($probe['preserveRows'] as $table => $records) {
        DB::table($table)->insert($records);
    }
    $tables = Schema::getTableListing(DB::connection()->getDatabaseName());
    $manager->deactivate($identifier);
    if ($manager->isModuleEnabled($identifier) || Schema::getTableListing(DB::connection()->getDatabaseName()) !== $tables) {
        throw new RuntimeException('Deactivation changed schema or did not disable the module.');
    }
    $manager->activate($identifier);
    if (! $manager->isModuleEnabled($identifier)) {
        throw new RuntimeException('Reactivation failed.');
    }
    $manager->uninstall($identifier);
    if ($manager->isModuleEnabled($identifier) || Schema::getTableListing(DB::connection()->getDatabaseName()) !== $tables) {
        throw new RuntimeException('Uninstallation must preserve owned schema and data.');
    }
    foreach ($probe['preserveRows'] as $table => $records) {
        foreach ($records as $record) {
            if (! DB::table($table)->where($record)->exists()) {
                throw new RuntimeException('Owned data was lost: '.$table);
            }
        }
    }
}

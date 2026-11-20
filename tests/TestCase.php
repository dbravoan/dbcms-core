<?php

declare(strict_types=1);

namespace Tests;

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Http\Middleware\EnsureCmsIsInstalled;
use DbCms\Core\Http\Middleware\RedirectIfInstalled;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BootstrapCacheIsolation;
use Tests\Support\PrivacyFixtures;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string>|null */
    private static ?array $migratedModules = null;

    private ?BootstrapCacheIsolation $bootstrapCaches = null;

    public function createApplication()
    {
        $this->bootstrapCaches ??= new BootstrapCacheIsolation;
        // Select providers before Laravel boots and registers the core catch-all
        // routes. Retain the framework's cached-state and trait handling.
        $modules = defined(static::class.'::TEST_MODULES') ? static::TEST_MODULES : [];
        $previous = [getenv('CMS_MODULES'), $_ENV['CMS_MODULES'] ?? null, $_SERVER['CMS_MODULES'] ?? null];
        $selection = implode(',', $modules);
        putenv('CMS_MODULES='.$selection);
        $_ENV['CMS_MODULES'] = $_SERVER['CMS_MODULES'] = $selection;
        try {
            $app = require dirname(__DIR__).'/.build/app/bootstrap/app.php';
            $app->make(Kernel::class)->bootstrap();

            return $app;
        } catch (Throwable $exception) {
            $this->bootstrapCaches->restore();
            $this->bootstrapCaches = null;
            throw $exception;
        } finally {
            putenv($previous[0] === false ? 'CMS_MODULES' : 'CMS_MODULES='.$previous[0]);
            if ($previous[1] === null) {
                unset($_ENV['CMS_MODULES']);
            } else {
                $_ENV['CMS_MODULES'] = $previous[1];
            }
            if ($previous[2] === null) {
                unset($_SERVER['CMS_MODULES']);
            } else {
                $_SERVER['CMS_MODULES'] = $previous[2];
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            // Laravel resolves these environment paths dynamically, including
            // during application-destroyed callbacks and explicit cache commands.
            $this->bootstrapCaches?->restore();
            $this->bootstrapCaches = null;
        }
    }

    protected function setUpTraits()
    {
        $this->withoutVite();
        $this->withoutMiddleware([EnsureCmsIsInstalled::class, RedirectIfInstalled::class]);
        Storage::fake('uploads');
        // A composition trait or PHPUnit class declares exactly its dependencies.
        // Providers must register migration paths before RefreshDatabase starts
        // its transaction; installing a package inside it would commit MySQL DDL.
        $modules = defined(static::class.'::TEST_MODULES') ? static::TEST_MODULES : [];
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            if (self::$migratedModules !== $modules) {
                RefreshDatabaseState::$migrated = false;
            }
            self::$migratedModules = $modules;
        }

        $traits = parent::setUpTraits();
        if ($modules !== []) {
            app(ModuleManager::class)->reconcileModules($modules);
            CmsModule::query()->whereIn('identifier', $modules)->update(['is_installed' => true, 'is_active' => true]);
        }
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $blocks = app(BlockPackageManager::class);
            $selected = defined(static::class.'::TEST_BLOCK_PACKAGES') ? static::TEST_BLOCK_PACKAGES : [];
            foreach ($selected as $identifier) {
                if (isset($blocks->all()[$identifier])) {
                    $blocks->setEnabled($identifier, true);
                }
            }
            $owners = defined(static::class.'::TEST_PRIVACY_OWNERS') ? static::TEST_PRIVACY_OWNERS : [];
            $processing = PrivacyFixtures::processingFor($modules, $owners);
            if ($processing !== []) {
                app(GeneralSettingsServiceInterface::class)->set('legal.processing', $processing, 'legal', 'json');
            }
        }

        return $traits;
    }
}

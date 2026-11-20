<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Forms\Blocks\CustomFormBlock;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\SearchEngine;
use DbCms\Core\Search\SearchIndexer;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CmsFixtures;
use Tests\Support\ComposerPackageInventory;
use Tests\Support\FreshMigrationDatabase;
use Tests\TestCase;

final class ModuleBlockAvailabilityIndexTest extends TestCase
{
    use FreshMigrationDatabase;

    public const TEST_MODULES = [];

    public function test_module_toggle_reindexes_authored_block_text_in_core_pages(): void
    {
        $this->withAvailabilitySetting(function (): void {
            CmsFixtures::localization(['es']);
            $page = Page::withoutEvents(function (): Page {
                $page = CmsFixtures::home();
                $page->updateQuietly(['published_at' => now()->subMinute()]);
                $section = $page->sections()->create(['type' => 'columns', 'status' => 'published', 'is_active' => true, 'order' => 1, 'styling_json' => ['layout' => '1']]);
                $section->translations()->create(['locale' => 'es', 'content_json' => ['columns' => [['blocks' => [['type' => 'widget_availability_probe', 'title' => 'Moduleonlyneedle']]]]]]);

                return $page;
            });
            app(SearchIndexer::class)->indexAll();
            $search = fn (): int => app(SearchEngine::class)->search('Moduleonlyneedle', 'es', market: 'es')->total;
            self::assertSame(1, $search());

            app(ModuleManager::class)->deactivate('availability-probe');
            self::assertSame(0, $search());
            self::assertSame(1, $page->sections()->count());
            app(ModuleManager::class)->activate('availability-probe');
            self::assertSame(1, $search());
            app(ModuleManager::class)->uninstall('availability-probe');
            self::assertSame(0, $search());
        });
    }

    public function test_failed_reindex_rolls_back_module_availability_and_index_together(): void
    {
        $this->withAvailabilitySetting(function (): void {
            CmsFixtures::localization(['es']);
            CmsFixtures::home();
            app(SearchIndexer::class)->indexAll();
            $before = SearchDocumentIndex::get()->toArray();
            $source = \Mockery::mock(SearchSourceInterface::class);
            $source->shouldReceive('type')->andReturn('failing-projection');
            $source->shouldReceive('documents')->andThrow(new \RuntimeException('Projection failed'));
            app(SearchSourceRegistryInterface::class)->register($source);

            try {
                app(ModuleManager::class)->deactivate('availability-probe');
                self::fail('A failed projection must abort the availability change.');
            } catch (\RuntimeException $error) {
                self::assertSame('Projection failed', $error->getMessage());
            }
            self::assertTrue(app(ModuleManager::class)->isModuleEnabled('availability-probe'));
            self::assertSame($before, SearchDocumentIndex::get()->toArray());
        });
    }

    public function test_availability_setting_reindexes_blocks_and_restarts_workers_after_commit(): void
    {
        $this->withAvailabilitySetting(function (): void {
            CmsFixtures::localization(['es']);
            $page = CmsFixtures::home();
            $section = $page->sections()->create(['type' => 'columns', 'status' => 'published', 'is_active' => true, 'order' => 0]);
            $section->translations()->create(['locale' => 'es', 'content_json' => ['columns' => [['blocks' => [['type' => 'widget_availability_probe', 'title' => 'Settingsneedle']]]]]]);
            app(SearchIndexer::class)->indexAll();
            $body = fn (): string => (string) SearchDocumentIndex::where('type', 'page')->where('entity_id', $page->id)->value('body');
            self::assertStringContainsString('Settingsneedle', $body());
            Cache::forget('illuminate:queue:restart');

            app(ModuleSettingsServiceInterface::class)->set('availability-probe', 'is_enabled', false);
            self::assertStringNotContainsString('Settingsneedle', $body());
            self::assertFalse(config('modules.availability-probe.is_enabled'));
            self::assertIsInt(Cache::get('illuminate:queue:restart'));
            app(ModuleSettingsServiceInterface::class)->set('availability-probe', 'is_enabled', true);
            self::assertStringContainsString('Settingsneedle', $body());
        });
    }

    public function test_failed_settings_projection_preserves_database_config_and_resolved_settings(): void
    {
        $this->withAvailabilitySetting(function (): void {
            $settings = app(ModuleSettingsServiceInterface::class);
            $settings->set('availability-probe', 'is_enabled', true);
            $source = \Mockery::mock(SearchSourceInterface::class);
            $source->shouldReceive('type')->andReturn('failing-settings-projection');
            $source->shouldReceive('documents')->andReturnUsing(static function () use ($settings): never {
                self::assertFalse($settings->get('availability-probe', 'is_enabled'));
                throw new \RuntimeException('Projection failed');
            });
            app(SearchSourceRegistryInterface::class)->register($source);
            Cache::forget('illuminate:queue:restart');
            try {
                $settings->set('availability-probe', 'is_enabled', false);
                self::fail('A failed projection must abort the settings change.');
            } catch (\RuntimeException $error) {
                self::assertSame('Projection failed', $error->getMessage());
            }
            self::assertTrue(app(ModuleManager::class)->isModuleEnabled('availability-probe'));
            self::assertTrue($settings->get('availability-probe', 'is_enabled'));
            self::assertTrue(config('modules.availability-probe.is_enabled'));
            self::assertNull(Cache::get('illuminate:queue:restart'));
        });
    }

    private function withAvailabilitySetting(callable $test): void
    {
        $inventory = new ComposerPackageInventory;
        $inventory->add('acme/availability-probe', 'dbcms-module', [
            'identifier' => 'availability-probe', 'name' => 'Availability probe',
            'provider' => 'Acme\\AvailabilityProbe\\Provider', 'availability_setting' => 'is_enabled',
            'settings' => ['is_enabled' => ['type' => 'boolean', 'default' => true]],
        ], ['dbravoan/dbcms-core' => '~0.0.1']);
        CmsModule::create([
            'identifier' => 'availability-probe', 'name' => 'Availability probe', 'version' => '1.0.0',
            'provider' => 'Acme\\AvailabilityProbe\\Provider', 'is_installed' => true, 'is_active' => true,
        ]);
        app(BlockRegistryInterface::class)->register(new class extends CustomFormBlock
        {
            public function getType(): string
            {
                return 'widget_availability_probe';
            }

            public function getRequiredModule(): ?string
            {
                return 'availability-probe';
            }
        });
        app()->instance(ComposerPackages::class, new ComposerPackages);
        app(ModuleManager::class)->discoverModules();
        try {
            $test();
        } finally {
            $inventory->restore();
        }
    }
}

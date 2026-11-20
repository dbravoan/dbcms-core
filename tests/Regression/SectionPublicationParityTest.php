<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Schema\PageEntitySchemas;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Capabilities\EntityTranslationWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['database.default' => 'section_publication', 'database.connections.section_publication' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    Schema::create('block_packages', function (Blueprint $table): void {
        $table->string('identifier')->primary();
        $table->boolean('is_enabled');
        $table->timestamps();
    });
    DB::table('block_packages')->insert(['identifier' => 'basic', 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    Schema::create('markets', static fn (Blueprint $table) => $table->id());
    Schema::create('languages', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->boolean('is_active');
    });
    (require Cms::path('src/Content/Pages/Database/Migrations/2026_09_01_000033_create_page_tables.php'))->up();
    (require Cms::path('src/Content/Pages/Database/Migrations/2026_11_19_000003_add_template_data_to_page_translations.php'))->up();
    (require Cms::path('database/migrations/2026_11_19_000001_create_translation_slug_locks_table.php'))->up();
    $locale = Mockery::mock(LocalizationServiceInterface::class);
    $locale->shouldReceive('defaultLocale')->andReturn('es');
    $locale->shouldReceive('url')->andReturn('/qa');
    app()->instance(LocalizationServiceInterface::class, $locale);
    $registry = app(EntitySchemaRegistryInterface::class);
    foreach (PageEntitySchemas::all() as $schema) {
        $registry->register($schema);
    }
    $this->entities = new EntityMutationService(new EntityReadService($registry, $locale), new EntityTranslationWriter);
    $audit = Mockery::mock(EditorialWorkflowInterface::class);
    $audit->shouldReceive('isEnabled')->andReturn(true);
    $audit->shouldReceive('pendingDraft')->andReturn(null);
    $audit->shouldReceive('requiresAudit')->andReturn(false);
    app()->instance(EditorialWorkflowInterface::class, $audit);
});

it('publishes the existing reviewed draft sections when publication does not resend blocks', function (): void {
    Model::withoutEvents(function (): void {
        $created = $this->entities->create('page', ['publish' => false, 'translations' => ['es' => ['title' => 'QA draft blocks']], 'sections_json' => ['es' => [['type' => 'rich_text', 'content' => 'QA visible reviewed content']]]]);
        $page = Page::findOrFail($created['id']);
        $section = $page->sections()->with('translations')->firstOrFail();
        $translation = $section->translations->firstOrFail()->getAttributes();
        expect($page->publishedSections()->count())->toBe(0);

        $this->entities->update('page', $page->id, ['publish' => true]);

        expect($page->fresh()->publishedSections()->count())->toBe(1)
            ->and($section->fresh()->published_at)->not->toBeNull()
            ->and($section->fresh()->translations->firstOrFail()->getAttributes())->toEqual($translation);
    });
});

it('does not expose sections when publication remains false', function (): void {
    Model::withoutEvents(function (): void {
        $created = $this->entities->create('page', ['publish' => false, 'translations' => ['es' => ['title' => 'QA private blocks']], 'sections_json' => ['es' => [['type' => 'rich_text', 'content' => 'Private']]]]);
        $this->entities->update('page', $created['id'], ['attributes' => ['template' => 'landing']]);
        expect(Page::findOrFail($created['id'])->publishedSections()->count())->toBe(0);
    });
});

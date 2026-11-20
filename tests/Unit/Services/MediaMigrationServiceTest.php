<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Models\Setting;
use DbCms\Core\Services\Media\MediaMigrationService;
use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaMigrationService $service;

    private MediaUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MediaMigrationService::class);
        $this->resolver = app(MediaUrlResolver::class);

        File::deleteDirectory($this->resolver->uploadDir());
        $this->resolver->uploadDir();
    }

    public function test_it_copies_local_files_and_rewrites_stored_urls(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');

        Setting::setValue('general.site_logo', '/uploads/photo.png', 'general', 'string');
        $pageId = DB::table('pages')->insertGetId(['template' => 'default', 'is_published' => false]);
        $sectionId = DB::table('page_sections')->insertGetId([
            'page_id' => $pageId, 'type' => 'rich_text',
            'styling_json' => json_encode(['/uploads/photo.png', '/uploads/other.png']),
        ]);

        $stats = $this->service->migrate();

        expect($stats['migrated'])->toBe(1);
        expect($stats['failed'])->toBe(0);
        Storage::disk('external')->assertExists('photo.png');

        // Local file is kept by default.
        expect(is_file($this->resolver->uploadDir().'/photo.png'))->toBeTrue();

        $expected = app(ExternalStorageService::class)->url('photo.png');

        expect(Setting::getValue('general.site_logo'))->toBe($expected);

        $gallery = json_decode((string) DB::table('page_sections')->where('id', $sectionId)->value('styling_json'), true);
        expect($gallery[0])->toBe($expected);
        expect($gallery[1])->toBe('/uploads/other.png');
    }

    public function test_it_is_idempotent_and_skips_files_already_present_with_the_same_size(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');

        $first = $this->service->migrate();
        $second = $this->service->migrate();

        expect($first['migrated'])->toBe(1);
        expect($second['migrated'])->toBe(0);
        expect($second['skipped'])->toBe(1);
    }

    public function test_it_prunes_local_files_only_after_a_verified_upload(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');

        $stats = $this->service->migrate(pruneLocal: true);

        expect($stats['pruned'])->toBe(1);
        expect(is_file($this->resolver->uploadDir().'/photo.png'))->toBeFalse();
        Storage::disk('external')->assertExists('photo.png');
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');
        Setting::setValue('general.site_logo', '/uploads/photo.png', 'general', 'string');

        $stats = $this->service->migrate(dryRun: true);

        expect($stats['migrated'])->toBe(1);
        Storage::disk('external')->assertMissing('photo.png');
        expect(Setting::getValue('general.site_logo'))->toBe('/uploads/photo.png');
        expect(is_file($this->resolver->uploadDir().'/photo.png'))->toBeTrue();
    }

    public function test_it_can_migrate_without_rewriting_urls(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');
        Setting::setValue('general.site_logo', '/uploads/photo.png', 'general', 'string');

        $stats = $this->service->migrate(rewriteUrls: false);

        expect($stats['migrated'])->toBe(1);
        expect($stats['rewritten'])->toBe(0);
        Storage::disk('external')->assertExists('photo.png');
        expect(Setting::getValue('general.site_logo'))->toBe('/uploads/photo.png');
    }

    public function test_rollback_restores_local_urls(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');
        Setting::setValue('general.site_logo', '/uploads/photo.png', 'general', 'string');

        $this->service->migrate();
        expect(Setting::getValue('general.site_logo'))->not->toBe('/uploads/photo.png');

        $result = $this->service->rollback();

        expect($result['rewritten'])->toBeGreaterThan(0);
        expect(Setting::getValue('general.site_logo'))->toBe(url('uploads/photo.png'));
    }

    public function test_it_only_migrates_the_requested_files(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');
        $this->putLocal('doc.pdf', 'pdf-bytes');

        $stats = $this->service->migrate(only: ['photo.png']);

        expect($stats['migrated'])->toBe(1);
        Storage::disk('external')->assertExists('photo.png');
        Storage::disk('external')->assertMissing('doc.pdf');
    }

    public function test_it_does_not_rewrite_rows_without_media_urls(): void
    {
        $this->configureExternalStorage();
        $this->putLocal('photo.png', 'photo-bytes');

        $pageId = DB::table('pages')->insertGetId(['template' => 'default', 'is_published' => false]);
        $sectionId = DB::table('page_sections')->insertGetId([
            'page_id' => $pageId, 'type' => 'rich_text',
            'styling_json' => json_encode(['layout' => 'grid', 'columns' => 3, 'title' => 'Hello']),
        ]);

        $stats = $this->service->migrate();

        // No stored reference points at the migrated file, so nothing is rewritten.
        expect($stats['rewritten'])->toBe(0);

        $gallery = json_decode((string) DB::table('page_sections')->where('id', $sectionId)->value('styling_json'), true);
        expect($gallery)->toEqual(['layout' => 'grid', 'columns' => 3, 'title' => 'Hello']);
    }

    private function configureExternalStorage(?string $publicUrl = 'https://cdn.example.com'): void
    {
        app(ExternalStorageService::class)->save([
            'provider' => 's3',
            'region' => 'eu-west-1',
            'bucket' => 'test-bucket',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret',
            'public_url' => $publicUrl,
            'root' => 'media',
        ]);

        Storage::fake('external');
    }

    private function putLocal(string $name, string $bytes): void
    {
        File::put($this->resolver->uploadDir().'/'.$name, $bytes);
    }
}

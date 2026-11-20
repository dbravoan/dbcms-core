<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateMediaToExternalCommandTest extends TestCase
{
    use RefreshDatabase;

    private MediaUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(MediaUrlResolver::class);

        File::deleteDirectory($this->resolver->uploadDir());
        $this->resolver->uploadDir();
    }

    public function test_it_fails_when_no_external_storage_is_configured(): void
    {
        $this->artisan('media:migrate-to-external')->assertFailed();
    }

    public function test_it_migrates_local_files_to_the_external_disk(): void
    {
        $this->configureExternalStorage();
        File::put($this->resolver->uploadDir().'/photo.png', 'photo-bytes');

        $this->artisan('media:migrate-to-external')->assertSuccessful();

        Storage::disk('external')->assertExists('photo.png');
        // Local file is preserved by default.
        expect(is_file($this->resolver->uploadDir().'/photo.png'))->toBeTrue();
    }

    public function test_it_can_prune_local_files(): void
    {
        $this->configureExternalStorage();
        File::put($this->resolver->uploadDir().'/photo.png', 'photo-bytes');

        $this->artisan('media:migrate-to-external', ['--prune-local' => true])->assertSuccessful();

        Storage::disk('external')->assertExists('photo.png');
        expect(is_file($this->resolver->uploadDir().'/photo.png'))->toBeFalse();
    }

    public function test_a_dry_run_uploads_nothing(): void
    {
        $this->configureExternalStorage();
        File::put($this->resolver->uploadDir().'/photo.png', 'photo-bytes');

        $this->artisan('media:migrate-to-external', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('external')->assertMissing('photo.png');
    }

    private function configureExternalStorage(): void
    {
        app(ExternalStorageService::class)->save([
            'provider' => 's3',
            'region' => 'eu-west-1',
            'bucket' => 'test-bucket',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret',
            'root' => 'media',
        ]);

        Storage::fake('external');
    }
}

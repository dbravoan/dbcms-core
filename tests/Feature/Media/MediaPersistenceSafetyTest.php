<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\MediaFile;
use DbCms\Core\Models\Setting;
use DbCms\Core\Services\Media\MediaManagerService;
use DbCms\Core\Services\Media\MediaMigrationService;
use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Media\SvgSanitizer;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function mediaDeletionWithRemoteFailure(bool $throw): MediaManagerService
{
    $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
    $values = ['external_storage_provider' => 's3', 'external_storage_bucket' => 'audit', 'external_storage_access_key' => 'audit', 'external_storage_secret_key' => Crypt::encryptString('audit')];
    $settings->shouldReceive('get')->andReturnUsing(fn ($key, $default = null) => $values[$key] ?? $default);
    $external = new ExternalStorageService($settings);
    $uploads = Storage::disk('uploads');
    $disk = Mockery::mock(FilesystemAdapter::class);
    $expectation = $disk->shouldReceive('delete')->with('remote.png')->once();
    if ($throw) {
        $expectation->andThrow(new RuntimeException('Remote failure'));
    } else {
        $expectation->andReturn(false);
    }
    Storage::shouldReceive('disk')->with('uploads')->andReturn($uploads);
    Storage::shouldReceive('disk')->with(ExternalStorageService::DISK)->andReturn($disk);
    Storage::shouldReceive('forgetDisk')->with(ExternalStorageService::DISK)->andReturnNull();

    return new MediaManagerService($external, app(MediaUrlResolver::class), app(SvgSanitizer::class));
}

test('binary image conversion leaves one asset and listing does not duplicate it', function (): void {
    $file = UploadedFile::fake()->image('original.png', 20, 20);
    $manager = app(MediaManagerService::class);
    $result = $manager->saveFromBinary((string) file_get_contents($file->getPathname()), 'png', 'Binary image');
    expect($result['filename'])->toEndWith('.webp');
    expect(File::files($manager->getUploadDir()))->toHaveCount(1);
    expect(MediaFile::count())->toBe(1);
    $manager->listMedia();
    expect(MediaFile::count())->toBe(1);
});

test('failed remote deletion retains metadata and local cache for retry', function (bool $throw): void {
    $record = MediaFile::create(['uuid' => (string) Str::uuid(), 'filename' => 'remote.png', 'original_name' => 'remote.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'url' => 'https://example.test/remote.png']);
    Storage::disk('uploads')->put('remote.png', 'cached');
    $manager = mediaDeletionWithRemoteFailure($throw);
    expect($manager->delete('remote.png'))->toBeFalse();
    expect(MediaFile::whereKey($record->id)->exists())->toBeTrue();
    expect(Storage::disk('uploads')->exists('remote.png'))->toBeTrue();
})->with([true, false]);

test('successful local deletion removes file and metadata together', function (): void {
    $manager = app(MediaManagerService::class);
    $result = $manager->saveFromBinary('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'svg', 'Delete me');
    expect($manager->delete($result['filename']))->toBeTrue();
    expect(MediaFile::whereKey($result['id'])->exists())->toBeFalse();
    expect(File::exists($manager->getUploadDir().'/'.$result['filename']))->toBeFalse();
});

test('the paginated library reads only registered files and reconciliation is idempotent', function (): void {
    Storage::disk('uploads')->put('library-local.txt', 'Fixture');
    $manager = app(MediaManagerService::class);
    expect($manager->paginateMedia([])['pagination']['total'])->toBe(0);
    $this->artisan('cms:media:reconcile')->assertSuccessful();
    $this->artisan('cms:media:reconcile')->assertSuccessful();

    expect($manager->paginateMedia(['search' => 'library-', 'type' => 'doc'])['pagination']['total'])->toBe(1)
        ->and($manager->paginateMedia(['search' => '%'])['pagination']['total'])->toBe(0)
        ->and(MediaFile::query()->count())->toBe(1);
});

test('rewriting a migrated media URL preserves edits made after the batch read', function (): void {
    $url = 'https://media.example.test/race.png';
    Storage::disk('uploads')->put('race.png', 'Fixture');
    $setting = Setting::setValue('media.race', 'Original '.$url);
    $edited = false;
    DB::listen(function (QueryExecuted $query) use ($setting, $url, &$edited): void {
        if (! $edited && str_contains($query->sql, 'select `id`, `value` from `settings`')) {
            $edited = true;
            DB::table('settings')->where('id', $setting->id)->update(['value' => 'Edited '.$url]);
        }
    });

    $result = app(MediaMigrationService::class)->rollback();

    expect($edited)->toBeTrue()
        ->and($result['rewritten'])->toBe(1)
        ->and($setting->fresh()->value)->toBe('Edited '.app(MediaUrlResolver::class)->localUrl('race.png'));
});

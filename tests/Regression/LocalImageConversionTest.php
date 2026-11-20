<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Media\MediaManagerService;
use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Media\SvgSanitizer;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('stores one converted image with a MIME type matching its WebP bytes', function (): void {
    $directory = sys_get_temp_dir().'/dbcms-media-audit-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory, 0755, true);
    $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
    $settings->shouldReceive('get')->with('external_storage_provider', 'local')->andReturn('local');
    $storage = new ExternalStorageService($settings);
    $service = Mockery::mock(MediaManagerService::class, [$storage, new MediaUrlResolver($storage), new SvgSanitizer])->makePartial();
    $service->shouldReceive('getUploadDir')->andReturn($directory);
    Schema::shouldReceive('hasTable')->with('media_files')->once()->andReturnFalse();
    try {
        $result = $service->upload(UploadedFile::fake()->image('audit.png', 64, 36));
        expect($result['mime_type'])->toBe('image/webp');
        expect(File::mimeType($directory.'/'.$result['filename']))->toBe($result['mime_type']);
        expect(File::files($directory))->toHaveCount(1);
    } finally {
        File::deleteDirectory($directory);
    }
});

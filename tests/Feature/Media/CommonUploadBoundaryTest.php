<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Media\MediaManagerService;
use DbCms\Core\Services\Media\MediaUploadService;
use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Media\SvgSanitizer;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\McpCoreComposition;

uses(McpCoreComposition::class);

test('shared media boundary rejects a misleading extension before storing it', function (): void {
    $file = UploadedFile::fake()->createWithContent('portrait.jpg', '<html><script>alert(1)</script></html>');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});

test('MCP upload rejects content that the backoffice also refuses', function (): void {
    expect(fn () => app(MediaUploadService::class)->mediaUpload([
        'filename' => 'portrait.jpg', 'content_base64' => base64_encode('<html>not an image</html>'),
    ]))->toThrow(ValidationException::class);
});

test('shared media boundary stores an actual image with truthful metadata', function (): void {
    $result = app(MediaManagerService::class)->upload(UploadedFile::fake()->image('portrait.png', 10, 10));
    expect($result['is_image'])->toBeTrue()
        ->and($result['mime_type'])->toBeIn(['image/png', 'image/webp'])
        ->and($result['width'])->toBe(10);
});

test('shared media boundary enforces the backoffice 50 MB limit', function (): void {
    $file = UploadedFile::fake()->create('oversized.txt', 51201, 'text/plain');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});

test('shared media boundary refuses a text payload renamed as an image', function (): void {
    $file = UploadedFile::fake()->createWithContent('portrait.png', 'Ordinary plain text');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});

test('generated binaries use the upload validation boundary', function (): void {
    expect(fn () => app(MediaManagerService::class)->saveFromBinary('not an image', 'png', 'Invalid'))
        ->toThrow(ValidationException::class);
});

test('generated binaries preserve title and explicit empty alternative text', function (): void {
    $file = UploadedFile::fake()->image('source.png', 12, 8);
    $result = app(MediaManagerService::class)->saveFromBinary((string) file_get_contents($file->getPathname()), 'png', 'Editorial image', '');
    expect($result['title'])->toBe('Editorial image')
        ->and($result['alt_text'])->toBe('')
        ->and($result['width'])->toBe(12)
        ->and($result['height'])->toBe(8);
});

test('URL imports reject local networks and data URLs', function (string $url): void {
    expect(fn () => app(MediaUploadService::class)->importImage(['url' => $url]))
        ->toThrow(ValidationException::class);
})->with(['http://127.0.0.1/image.png', 'http://192.168.0.1/image.png', 'data:image/png;base64,aGVsbG8=']);

test('URL imports store actual image bytes and metadata in the library', function (): void {
    $file = UploadedFile::fake()->image('remote.png', 14, 9);
    Http::preventStrayRequests();
    Http::fake([
        'https://8.8.8.8/image.png' => Http::response(
            file_get_contents($file->getPathname()), 200, ['Content-Type' => 'image/png'],
        ),
    ]);
    $result = app(MediaUploadService::class)->importImage([
        'url' => 'https://8.8.8.8/image.png', 'title' => 'Imported portrait', 'alt_text' => 'A portrait',
    ]);
    expect($result['uploaded'])->toBeTrue()
        ->and($result['media']['title'])->toBe('Imported portrait')
        ->and($result['media']['width'])->toBe(14)
        ->and($result['media']['alt_text'])->toBe('A portrait');
});

test('URL image imports refuse video responses before persistence', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://8.8.8.8/video.mp4' => Http::response('video bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);
    expect(fn () => app(MediaUploadService::class)->importImage(['url' => 'https://8.8.8.8/video.mp4']))
        ->toThrow(ValidationException::class);
});

test('uploaded imported and generated images share configured external storage', function (string $source): void {
    $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
    $values = [
        'external_storage_provider' => 's3', 'external_storage_bucket' => 'images',
        'external_storage_access_key' => 'fixture',
        'external_storage_secret_key' => Crypt::encryptString('fixture'),
    ];
    $settings->shouldReceive('get')->andReturnUsing(fn ($key, $default = null) => $values[$key] ?? $default);
    $disk = Storage::fake('external');
    $manager = new MediaManagerService(
        new ExternalStorageService($settings),
        app(MediaUrlResolver::class),
        app(SvgSanitizer::class),
    );
    $image = UploadedFile::fake()->image('sample.png', 16, 10);
    $bytes = (string) file_get_contents($image->getPathname());
    Http::preventStrayRequests();
    Http::fake(['https://8.8.8.8/image.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);
    $result = match ($source) {
        'upload' => $manager->upload($image),
        'import' => (new MediaUploadService($manager))->importImage(['url' => 'https://8.8.8.8/image.png'])['media'],
        'generated' => $manager->saveFromBinary($bytes, 'png', 'Generated sample'),
    };
    $disk->assertExists($result['filename']);
    expect($result['url'])->toBe($disk->url($result['filename']))
        ->and($result['width'])->toBe(16)
        ->and($result['height'])->toBe(10);
    $this->assertDatabaseHas('media_files', ['id' => $result['id'], 'url' => $result['url']]);
})->with(['upload', 'import', 'generated']);

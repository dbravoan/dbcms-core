<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadsFallbackTest extends TestCase
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

    public function test_it_serves_an_existing_local_file(): void
    {
        File::put($this->resolver->uploadDir().'/local.png', 'local-bytes');

        $response = $this->get('/uploads/local.png');

        $response->assertOk();
        expect($response->streamedContent() ?: $response->getContent())->toContain('local-bytes');
    }

    public function test_it_serves_the_placeholder_as_a_public_asset_without_session_cookies(): void
    {
        $this->get('/images/placeholder.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeaderMissing('Set-Cookie');
    }

    public function test_it_redirects_to_the_cdn_when_the_file_only_exists_externally(): void
    {
        $this->configureExternalStorage(publicUrl: 'https://cdn.example.com');
        Storage::fake('external');
        Storage::disk('external')->put('remote.png', 'remote-bytes');

        $response = $this->get('/uploads/remote.png');

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('remote.png');
    }

    public function test_it_streams_from_the_external_disk_when_no_public_url_is_configured(): void
    {
        $this->configureExternalStorage();
        Storage::fake('external');
        Storage::disk('external')->put('remote.png', 'remote-bytes');

        $response = $this->get('/uploads/remote.png');

        $response->assertOk();
        expect($response->streamedContent())->toContain('remote-bytes');
    }

    public function test_it_returns_404_when_the_file_exists_nowhere(): void
    {
        $this->get('/uploads/missing.png')->assertNotFound();
    }

    public function test_it_rejects_path_traversal_attempts(): void
    {
        $this->get('/uploads/..%2F..%2F.env')->assertNotFound();
    }

    private function configureExternalStorage(?string $publicUrl = null): void
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
    }
}

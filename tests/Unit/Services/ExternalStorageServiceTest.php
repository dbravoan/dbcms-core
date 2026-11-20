<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Models\Setting;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExternalStorageService $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = app(ExternalStorageService::class);
        $this->storage->save(['provider' => 'local']);
    }

    public function test_it_defaults_to_local_storage_when_unconfigured(): void
    {
        expect($this->storage->provider())->toBe('local');
        expect($this->storage->isEnabled())->toBeFalse();
        expect($this->storage->isConfigured())->toBeFalse();
        expect($this->storage->diskName())->toBe('local');
    }

    public function test_it_exposes_all_supported_providers(): void
    {
        $providers = $this->storage->providers();

        expect($providers)->toHaveKeys(['local', 's3', 'ovh', 'r2', 'minio']);
        expect($providers['local']['driver'])->toBe('local');
        expect($providers['ovh']['driver'])->toBe('s3');
        expect($providers['r2']['path_style'])->toBeTrue();

        $labels = $this->storage->providerLabels();
        expect(array_keys($labels))->toBe(array_keys($providers));
        expect($labels['s3'])->toBe('Amazon S3');
    }

    public function test_it_becomes_configured_once_credentials_are_saved_and_secret_is_encrypted(): void
    {
        $this->storage->save([
            'provider' => 'ovh',
            'endpoint' => 'https://s3.eu-west-par.io.cloud.ovh.net',
            'region' => 'eu-west-par',
            'bucket' => 'dbcms-media',
            'access_key' => 'AKIAEXAMPLE',
            'secret_key' => 'super-secret-value',
            'path_style' => false,
            'public_url' => 'https://cdn.example.com',
            'root' => 'media',
        ]);

        expect($this->storage->provider())->toBe('ovh');
        expect($this->storage->isConfigured())->toBeTrue();
        expect($this->storage->diskName())->toBe(ExternalStorageService::DISK);
        expect($this->storage->secret())->toBe('super-secret-value');

        // The secret must never be stored in plaintext.
        $raw = Setting::where('key', 'external_storage_secret_key')->value('value');
        expect($raw)->not->toBe('super-secret-value');
        expect($raw)->not->toContain('super-secret-value');

        // Masked value is exposed for display only.
        expect($this->storage->configuration()['secret_key'])->toBe('••••••••');
    }

    public function test_saving_with_an_empty_or_masked_secret_keeps_the_current_one(): void
    {
        $this->storage->save([
            'provider' => 's3',
            'bucket' => 'bucket-a',
            'access_key' => 'key-a',
            'secret_key' => 'original-secret',
        ]);

        $this->storage->save([
            'provider' => 's3',
            'bucket' => 'bucket-b',
            'access_key' => 'key-a',
            'secret_key' => '',
        ]);

        expect($this->storage->setting('bucket'))->toBe('bucket-b');
        expect($this->storage->secret())->toBe('original-secret');

        $this->storage->save(['provider' => 's3', 'secret_key' => '••••••••']);
        expect($this->storage->secret())->toBe('original-secret');
    }

    public function test_register_disk_builds_an_s3_compatible_disk_config(): void
    {
        $this->storage->save([
            'provider' => 'r2',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'region' => 'auto',
            'bucket' => 'r2-bucket',
            'access_key' => 'r2-key',
            'secret_key' => 'r2-secret',
            'path_style' => true,
            'public_url' => 'https://cdn.example.com',
            'root' => 'documents',
        ]);

        $this->storage->registerDisk();

        $disk = config('filesystems.disks.'.ExternalStorageService::DISK);

        expect($disk)->toBeArray();
        expect($disk['driver'])->toBe('s3');
        expect($disk['bucket'])->toBe('r2-bucket');
        expect($disk['endpoint'])->toBe('https://account.r2.cloudflarestorage.com');
        expect($disk['use_path_style_endpoint'])->toBeTrue();
        expect($disk['root'])->toBe('documents');
        expect($disk['url'])->toBe('https://cdn.example.com');
        expect($disk['secret'])->toBe('r2-secret');
    }

    public function test_test_connection_reports_failure_when_unconfigured(): void
    {
        $result = $this->storage->testConnection();

        expect($result['success'])->toBeFalse();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Regression;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class StorageCredentialBoundaryTest extends TestCase
{
    public function test_a_resolved_disk_uses_rotated_configuration_without_restarting_the_process(): void
    {
        $values = [
            'external_storage_provider' => 'minio',
            'external_storage_endpoint' => 'https://first.example.test',
            'external_storage_bucket' => 'media',
            'external_storage_access_key' => 'old-access',
            'external_storage_secret_key' => Crypt::encryptString('old-secret'),
            'external_storage_public_url' => 'https://first.example.test',
        ];
        $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
        $settings->shouldReceive('get')->andReturnUsing(static function (string $key, mixed $default = null) use (&$values): mixed {
            return $values[$key] ?? $default;
        });
        $storage = new ExternalStorageService($settings);
        self::assertStringStartsWith('https://first.example.test/', $storage->url('photo.jpg'));
        $original = Storage::disk(ExternalStorageService::DISK);

        $values['external_storage_public_url'] = 'https://second.example.test';
        $values['external_storage_access_key'] = 'new-access';
        $values['external_storage_secret_key'] = Crypt::encryptString('new-secret');

        self::assertStringStartsWith('https://second.example.test/', $storage->url('photo.jpg'));
        self::assertNotSame($original, Storage::disk(ExternalStorageService::DISK));
        self::assertSame('new-access', Storage::disk(ExternalStorageService::DISK)->getConfig()['key']);
    }

    public function test_changing_destination_does_not_reuse_credentials_or_accept_masked_replacements(): void
    {
        $saved = [];
        $values = ['external_storage_provider' => 'minio', 'external_storage_endpoint' => 'https://first.example.test', 'external_storage_access_key' => 'old-access', 'external_storage_secret_key' => Crypt::encryptString('old-secret')];
        $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
        $settings->shouldReceive('get')->andReturnUsing(static function (string $key, mixed $default = null) use (&$values): mixed {
            return $values[$key] ?? $default;
        });
        $settings->shouldReceive('setMany')->once()->andReturnUsing(function (array $payload) use (&$saved): void {
            $saved = $payload;
        });
        (new ExternalStorageService($settings))->save(['endpoint' => 'https://second.example.test', 'access_key' => '••••••••', 'secret_key' => '••••••••']);
        self::assertSame('', $saved['external_storage_access_key']);
        self::assertSame('', $saved['external_storage_secret_key']);
    }

    public function test_an_explicit_new_pair_is_encrypted_and_configuration_never_exposes_credentials(): void
    {
        $values = ['external_storage_provider' => 'minio', 'external_storage_endpoint' => 'https://first.example.test'];
        $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
        $settings->shouldReceive('get')->andReturnUsing(static function (string $key, mixed $default = null) use (&$values): mixed {
            return $values[$key] ?? $default;
        });
        $settings->shouldReceive('setMany')->once()->andReturnUsing(function (array $payload) use (&$values): void {
            $values = array_replace($values, $payload);
        });
        $storage = new ExternalStorageService($settings);
        $storage->save(['endpoint' => 'https://second.example.test', 'bucket' => 'media', 'access_key' => 'new-access', 'secret_key' => 'new-secret']);
        self::assertNotSame('new-secret', $values['external_storage_secret_key']);
        self::assertSame('new-secret', Crypt::decryptString($values['external_storage_secret_key']));
        self::assertSame('••••••••', $storage->configuration()['access_key']);
        self::assertSame('••••••••', $storage->configuration()['secret_key']);
    }
}

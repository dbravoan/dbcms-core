<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Storage;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Configures an optional S3-compatible disk (Amazon S3, OVH Object Storage,
 * Cloudflare R2, MinIO...) from the backoffice so uploaded documents can be
 * stored off-host. Falls back to the local disk when unconfigured.
 */
final class ExternalStorageService
{
    public const GROUP = 'external_storage';

    public const DISK = 'external';

    /**
     * Selectable S3-compatible providers with sensible defaults.
     *
     * @var array<string, array{driver: string, endpoint: string|null, region: string, path_style: bool}>
     */
    public const PROVIDERS = [
        'local' => ['driver' => 'local', 'endpoint' => null, 'region' => '', 'path_style' => false],
        's3' => ['driver' => 's3', 'endpoint' => null, 'region' => 'eu-west-1', 'path_style' => false],
        'ovh' => ['driver' => 's3', 'endpoint' => 'https://s3.eu-west-par.io.cloud.ovh.net', 'region' => 'eu-west-par', 'path_style' => false],
        'r2' => ['driver' => 's3', 'endpoint' => 'https://<accountid>.r2.cloudflarestorage.com', 'region' => 'auto', 'path_style' => true],
        'minio' => ['driver' => 's3', 'endpoint' => 'https://localhost:9000', 'region' => 'us-east-1', 'path_style' => true],
    ];

    /**
     * @var array<string, string>
     */
    private const FIELD_TYPES = [
        'provider' => 'string',
        'endpoint' => 'string',
        'region' => 'string',
        'bucket' => 'string',
        'access_key' => 'string',
        'secret_key' => 'string',
        'path_style' => 'boolean',
        'public_url' => 'string',
        'root' => 'string',
    ];

    public function __construct(
        private readonly GeneralSettingsServiceInterface $settings
    ) {}

    /**
     * @return array<string, array{driver: string, endpoint: string|null, region: string, path_style: bool}>
     */
    public function providers(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Translated labels keyed by provider identifier.
     *
     * @return array<string, string>
     */
    public function providerLabels(): array
    {
        $labels = [];

        foreach (array_keys(self::PROVIDERS) as $key) {
            $labels[$key] = (string) __('admin.connections.provider_'.$key);
        }

        return $labels;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get("external_storage_{$key}", $default);
    }

    /**
     * The decrypted secret key (stored encrypted at rest).
     */
    public function secret(): string
    {
        $stored = (string) $this->setting('secret_key', '');

        if ($stored === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($stored);
        } catch (Throwable) {
            return ''; // Invalid ciphertext cannot be used as credentials.
        }
    }

    public function provider(): string
    {
        $provider = (string) $this->setting('provider', 'local');

        return isset(self::PROVIDERS[$provider]) ? $provider : 'local';
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return [
            'provider' => $this->provider(),
            'endpoint' => (string) $this->setting('endpoint', ''),
            'region' => (string) $this->setting('region', ''),
            'bucket' => (string) $this->setting('bucket', ''),
            'access_key' => $this->setting('access_key', '') !== '' ? '••••••••' : '',
            'secret_key' => $this->secret() !== '' ? '••••••••' : '',
            'path_style' => (bool) $this->setting('path_style', false),
            'public_url' => (string) $this->setting('public_url', ''),
            'root' => (string) $this->setting('root', 'media'),
        ];
    }

    public function isEnabled(): bool
    {
        return self::PROVIDERS[$this->provider()]['driver'] !== 'local';
    }

    public function isConfigured(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return trim((string) $this->setting('bucket', '')) !== ''
            && trim((string) $this->setting('access_key', '')) !== ''
            && trim($this->secret()) !== '';
    }

    /**
     * The disk media/documents should be written to.
     */
    public function diskName(): string
    {
        return $this->isConfigured() ? self::DISK : 'local';
    }

    public function root(): string
    {
        return trim((string) $this->setting('root', 'media'), '/');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $payload = [];
        $destinationChanged = (isset($data['endpoint']) && rtrim((string) $data['endpoint'], '/') !== rtrim((string) $this->setting('endpoint', ''), '/'))
            || (isset($data['provider']) && (string) $data['provider'] !== $this->provider());
        $newSecret = is_string($data['secret_key'] ?? null) && $data['secret_key'] !== '' && ! str_starts_with($data['secret_key'], '••••');
        $newAccess = is_string($data['access_key'] ?? null) && $data['access_key'] !== '' && ! str_starts_with($data['access_key'], '••••');
        if ($destinationChanged && (! $newSecret || ! $newAccess)) {
            $payload['external_storage_secret_key'] = '';
            $payload['external_storage_access_key'] = '';
            unset($data['access_key'], $data['secret_key']);
        }

        foreach (self::FIELD_TYPES as $key => $type) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            // Never overwrite the stored secret with the masked placeholder.
            if ($key === 'access_key' && is_string($value) && str_starts_with($value, '••••')) {
                continue;
            }
            if ($key === 'secret_key') {
                if (! is_string($value) || $value === '' || str_starts_with($value, '••••')) {
                    continue;
                }

                $payload['external_storage_secret_key'] = Crypt::encryptString($value);

                continue;
            }

            $payload["external_storage_{$key}"] = $type === 'boolean' ? (bool) $value : (string) $value;
        }

        if ($payload !== []) {
            $this->settings->setMany($payload, self::GROUP);
        }
    }

    /**
     * Whether a stored object exists on the external disk (path relative to root).
     */
    public function exists(string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $this->registerDisk();

        try {
            return Storage::disk(self::DISK)->exists(ltrim($path, '/'));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The configured public URL / CDN prefix, or an empty string when unset.
     */
    public function publicUrlPrefix(): string
    {
        return rtrim(trim((string) $this->setting('public_url', '')), '/');
    }

    /**
     * Register the S3-compatible disk at runtime from the stored settings.
     */
    public function registerDisk(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        // Respect a disk provided elsewhere (e.g. Storage::fake in tests).
        $existing = config('filesystems.disks.'.self::DISK);
        if (is_array($existing) && ! ($existing['_external_managed'] ?? false)) {
            return;
        }
        if (is_array($existing) && ($existing['_external_managed'] ?? false)) {
            $resolved = Storage::disk(self::DISK);
            if (! $resolved instanceof FilesystemAdapter || ! ($resolved->getConfig()['_external_managed'] ?? false)) {
                return;
            }
        }

        $provider = self::PROVIDERS[$this->provider()];

        $disk = [
            'driver' => 's3',
            'key' => (string) $this->setting('access_key', ''),
            'secret' => $this->secret(),
            'region' => (string) ($this->setting('region', '') ?: $provider['region'] ?: 'auto'),
            'bucket' => (string) $this->setting('bucket', ''),
            'root' => $this->root(),
            'use_path_style_endpoint' => (bool) $this->setting('path_style', $provider['path_style']),
            'visibility' => 'public',
            'throw' => true,
            '_external_managed' => true,
        ];

        $endpoint = (string) ($this->setting('endpoint', '') ?: $provider['endpoint'] ?? '');
        if ($endpoint !== '') {
            $disk['endpoint'] = $endpoint;
        }

        $publicUrl = trim((string) $this->setting('public_url', ''));
        if ($publicUrl !== '') {
            // The S3 adapter prepends the disk root when building URLs.
            $disk['url'] = rtrim($publicUrl, '/');
        }

        if ($existing !== $disk) {
            if ($existing !== null) {
                Storage::forgetDisk(self::DISK);
            }
            config(['filesystems.disks.'.self::DISK => $disk]);
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => (string) __('admin.connections.test_not_configured')];
        }

        $previous = config('filesystems.disks.'.self::DISK);
        $this->registerDisk();

        try {
            $disk = Storage::disk(self::DISK);
            $probe = '_dbcms_connection_test.txt';

            $disk->put($probe, 'ok');
            $ok = $disk->exists($probe);
            $disk->delete($probe);

            return [
                'success' => $ok,
                'message' => $ok
                    ? (string) __('admin.connections.test_success')
                    : (string) __('admin.connections.test_write_failed'),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => __('admin.connections.test_error').': '.$e->getMessage()];
        } finally {
            if ($previous === null) {
                config(['filesystems.disks.'.self::DISK => null]);
            }
        }
    }

    /**
     * Public URL for a document stored on the active disk (path relative to root).
     */
    public function url(string $path): string
    {
        $path = ltrim($path, '/');

        if (! $this->isConfigured()) {
            return url('uploads/'.$path);
        }

        $this->registerDisk();

        try {
            return Storage::disk(self::DISK)->url($path);
        } catch (Throwable) {
            $segments = array_filter([
                trim((string) $this->setting('public_url', ''), '/'),
                $this->root(),
                $path,
            ], static fn (string $segment): bool => $segment !== '');

            return implode('/', $segments);
        }
    }
}

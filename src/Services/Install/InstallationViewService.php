<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Contracts\Services\InstallationViewServiceInterface;

final class InstallationViewService implements InstallationViewServiceInterface
{
    public function __construct(private readonly InstallationEnvironmentServiceInterface $environment, private readonly BrandRegistry $brands) {}

    /** @return array<string, mixed> */
    public function requirements(): array
    {
        // 1. Proactively ensure .env and APP_KEY exist
        $envCreated = $this->environment->ensure();

        // 2. System PHP Extensions check
        $requirements = [
            'PHP Version (>= 8.4)' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL Driver' => extension_loaded('pdo_mysql'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'Mbstring Extension' => extension_loaded('mbstring'),
            'Tokenizer Extension' => extension_loaded('tokenizer'),
            'DOM Extension' => extension_loaded('dom'),
            'Intl Extension' => extension_loaded('intl'),
            'Fileinfo Extension' => extension_loaded('fileinfo'),
            'Ctype Extension' => extension_loaded('ctype'),
            'JSON Extension' => extension_loaded('json'),
            'cURL Extension' => extension_loaded('curl'),
            'GD / Image Extension' => extension_loaded('gd'),
        ];

        // 3. Writable directory permissions check
        $permissions = [
            'storage/' => is_writable(storage_path()),
            'storage/logs/' => is_writable(storage_path('logs')),
            'storage/framework/' => is_writable(storage_path('framework')),
            'bootstrap/cache/' => is_writable(base_path('bootstrap/cache')),
            '.env (archivo de configuración)' => is_writable(base_path('.env')) || is_writable(base_path()),
        ];

        $allRequirementsMet = ! in_array(false, $requirements, true);
        $allPermissionsMet = ! in_array(false, $permissions, true);

        return [
            'requirements' => $requirements,
            'permissions' => $permissions,
            'envCreated' => $envCreated,
            'canProceed' => $allRequirementsMet && $allPermissionsMet,
        ];
    }

    /** @return array<string, mixed> */
    public function database(): array
    {
        $currentConfig = [
            'host' => (string) config('database.connections.mysql.host', '127.0.0.1'),
            'port' => (string) config('database.connections.mysql.port', '3306'),
            'database' => (string) config('database.connections.mysql.database', 'dbcms'),
            'username' => (string) config('database.connections.mysql.username', 'root'),
            'password' => (string) config('database.connections.mysql.password', ''),
        ];

        return [
            'config' => $currentConfig,
        ];
    }

    /** @return array<string, mixed> */
    public function administrator(): array
    {
        $brandList = $this->brands->brands();
        $frontThemes = $this->brands->frontThemes();

        return [
            'defaultAdminPath' => (string) config('cms.admin_path', 'admin'),
            'defaultAppUrl' => rtrim((string) config('app.url', url('/')), '/'),
            'installBrands' => $brandList,
            'brandChoice' => $this->resolveChoice($brandList, (string) config('cms.admin_theme', 'dbcms')),
            'frontThemes' => $frontThemes,
            'themeChoice' => $this->resolveChoice($frontThemes, (string) config('cms.theme', 'db-cyber')),
        ];
    }

    /**
     * Decides how a list of options should be presented to the installer.
     *
     * @param  array<int, array<string, mixed>>  $options
     * @return array{count: int, mode: string, selected: string|null}
     */
    private function resolveChoice(array $options, string $preferred): array
    {
        $identifiers = array_map(
            static fn (array $option): string => (string) $option['identifier'],
            $options
        );

        $selected = in_array($preferred, $identifiers, true)
            ? $preferred
            : ($identifiers[0] ?? null);

        return [
            'count' => count($options),
            'mode' => match (true) {
                count($options) === 0 => 'none',
                count($options) === 1 => 'single',
                default => 'multiple',
            },
            'selected' => $selected,
        ];
    }

    /** @return array<string, mixed> */
    public function complete(): array
    {
        $adminPath = (string) config('cms.admin_path', 'admin');

        return [
            'adminPath' => $adminPath,
            'loginUrl' => url("/{$adminPath}/login"),
        ];
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Contracts\Services\InstallationAdminServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\DTOs\Install\InstallationAdminDTO;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

final class InstallationAdminService implements InstallationAdminServiceInterface
{
    public function __construct(private readonly InstallationEnvironmentServiceInterface $environment, private readonly BrandRegistry $brands, private readonly LegalPageService $legal) {}

    public function finalize(InstallationAdminDTO $admin, bool $force = false): void
    {
        $this->validate($admin);
        if ($admin->company === null) {
            throw new \InvalidArgumentException('Company privacy information is required before installation.');
        }
        $lockPath = storage_path('install-finalize.lock');
        if (is_link($lockPath)) {
            throw new \RuntimeException('The installation lock cannot be a symbolic link.');
        }
        $lock = fopen($lockPath, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock administrator finalization.');
        }
        chmod($lockPath, 0600);
        $installed = storage_path('installed');
        $envPath = realpath(app()->environmentFilePath()) ?: app()->environmentFilePath();
        $previousEnvironment = File::isFile($envPath) ? File::get($envPath) : null;
        $previousLock = File::isFile($installed) ? File::get($installed) : null;
        try {
            if (is_dir($installed) || is_link($installed) || ($previousLock !== null && ! $force)) {
                throw new \RuntimeException('The installation is already sealed or the destination is invalid.');
            }
            DB::transaction(function () use ($admin, $force, $installed): void {
                $existing = User::where('email', $admin->email)->lockForUpdate()->first();
                if ($existing !== null && ! $force) {
                    throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
                }
                $user = $existing ?? new User(['email' => $admin->email]);
                $user->fill(['name' => $admin->name, 'password' => $admin->password])->save();
                $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador', 'description' => 'Acceso administrativo total']);
                $user->roles()->syncWithoutDetaching([$role->id]);
                $this->legal->provision($admin->company ?? throw new \InvalidArgumentException('Company privacy information is required.'));
                $this->environment->write(['APP_NAME' => $admin->siteName, 'APP_URL' => $admin->appUrl !== '' ? $admin->appUrl : (string) config('app.url'), 'ADMIN_PATH' => $admin->adminPath, 'CMS_BRAND' => $admin->brand, 'CMS_THEME' => $admin->frontTheme, 'LOG_CHANNEL' => 'daily', 'LOG_DAILY_DAYS' => (string) $this->legal->requireProcessing('core')['retention_days']]);
                File::replace($installed, json_encode(['installed_at' => now()->toIso8601String(), 'admin_email' => $admin->email, 'admin_path' => $admin->adminPath, 'site_name' => $admin->siteName, 'brand' => $admin->brand, 'front_theme' => $admin->frontTheme, 'version' => (string) config('cms.version'), 'platform' => 'dbCMS Enterprise'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), 0600);
                if (app()->configurationIsCached() && Artisan::call('config:clear') !== 0) {
                    throw new \RuntimeException('Could not invalidate the installation configuration cache.');
                }
            });
            config(['app.url' => $admin->appUrl !== '' ? $admin->appUrl : (string) config('app.url'), 'cms.admin_theme' => $admin->brand, 'cms.theme' => $admin->frontTheme, 'cms.name' => $admin->siteName]);
        } catch (\Throwable $error) {
            if ($previousEnvironment !== null && (! File::isFile($envPath) || File::get($envPath) !== $previousEnvironment)) {
                File::replace($envPath, $previousEnvironment, 0600);
            } elseif ($previousEnvironment === null && File::isFile($envPath)) {
                File::delete($envPath);
            }
            if ($previousLock !== null) {
                File::replace($installed, $previousLock, 0600);
            } elseif (File::isFile($installed)) {
                File::delete($installed);
            }
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validate(InstallationAdminDTO $admin): void
    {
        if (trim($admin->name) === '' || trim($admin->siteName) === '' || filter_var($admin->email, FILTER_VALIDATE_EMAIL) === false || strlen($admin->password) < 8 || ($admin->appUrl !== '' && (filter_var($admin->appUrl, FILTER_VALIDATE_URL) === false || ! in_array((string) parse_url($admin->appUrl, PHP_URL_SCHEME), ['http', 'https'], true))) || ! preg_match('/\A[a-zA-Z0-9_\-]+\z/', $admin->adminPath) || ! in_array($admin->brand, $this->brands->brandIdentifiers(), true) || ($this->brands->frontThemeIdentifiers() !== [] && ! in_array($admin->frontTheme, $this->brands->frontThemeIdentifiers(), true))) {
            throw new \InvalidArgumentException('Invalid administrator or installation settings.');
        }
        foreach ([$admin->siteName, $admin->appUrl, $admin->adminPath, $admin->brand, $admin->frontTheme] as $value) {
            EnvValueSerializer::encode($value);
        }
    }
}

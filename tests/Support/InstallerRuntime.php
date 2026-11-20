<?php

declare(strict_types=1);

namespace Tests\Support;

use DbCms\Core\Http\Middleware\EnsureCmsIsInstalled;
use DbCms\Core\Http\Middleware\RedirectIfInstalled;
use Illuminate\Support\Facades\File;

trait InstallerRuntime
{
    use FreshMigrationDatabase;

    protected const INSTALL_TOKEN = '1243124312431243124312431243124312431243124312431243124312431243';

    private string $installerRuntime;

    private string $installerOriginalEnvironment;

    private string $installerOriginalStorage;

    private string $installerOriginalPublic;

    /** @var array<string,string> */
    private array $installerOriginalLinks;

    protected function beforeRefreshingDatabase(): void
    {
        IsolatedTestDatabase::assertConnection();
        $this->installerOriginalEnvironment = app()->environmentPath();
        $this->installerOriginalStorage = app()->storagePath();
        $this->installerOriginalPublic = app()->publicPath();
        $this->installerOriginalLinks = config('filesystems.links', []);
        $this->installerRuntime = sys_get_temp_dir().'/dbcms-installer-'.bin2hex(random_bytes(8));
        foreach (['public', 'storage/app/public', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
            File::ensureDirectoryExists($this->installerRuntime.'/'.$directory, 0700);
        }
        File::put($this->installerRuntime.'/.env', 'APP_KEY='.(string) config('app.key')."\n");
        app()->useEnvironmentPath($this->installerRuntime)->useStoragePath($this->installerRuntime.'/storage');
        app()->usePublicPath($this->installerRuntime.'/public');
        config(['view.compiled' => storage_path('framework/views'), 'filesystems.links' => [public_path('storage') => storage_path('app/public')]]);
        $this->withoutVite();
        $this->withMiddleware([EnsureCmsIsInstalled::class, RedirectIfInstalled::class]);
        config(['cms.install_token' => self::INSTALL_TOKEN]);
        $this->withSession(['install.operator' => hash('sha256', self::INSTALL_TOKEN)]);
    }

    protected function tearDownInstallerRuntime(): void
    {
        app()->useEnvironmentPath($this->installerOriginalEnvironment)->useStoragePath($this->installerOriginalStorage);
        app()->usePublicPath($this->installerOriginalPublic);
        config(['filesystems.links' => $this->installerOriginalLinks]);
        File::deleteDirectory($this->installerRuntime);
    }

    /** Exposes a generated README only for the duration of an installation assertion. */
    protected function withPendingProjectReadme(callable $assertion, int $mode = 0644): void
    {
        $path = base_path('README.md');
        $previous = File::exists($path) ? File::get($path) : null;
        $previousMode = $previous === null ? false : fileperms($path);
        $original = "# Aplicación de prueba\n\n<!-- dbcms:site:pending -->\nLa identidad del sitio se añadirá aquí al completar la instalación.\n<!-- /dbcms:site -->\n\nTexto del proyecto.\n";
        File::replace($path, $original, 0644);
        chmod($path, $mode);
        try {
            $assertion($path, $original);
        } finally {
            if ($previous === null) {
                File::delete($path);
            } else {
                File::replace($path, $previous, 0644);
                if ($previousMode !== false) {
                    chmod($path, $previousMode & 0777);
                }
            }
        }
    }
}

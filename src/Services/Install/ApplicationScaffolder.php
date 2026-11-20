<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Cms;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Extensions\ComposerPackages;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;

/** Integration files for a new Laravel application; never migrates or queries data. */
final class ApplicationScaffolder
{
    public function __construct(
        private readonly ComposerPackages $packages,
        private readonly AdminThemeServiceInterface $adminThemes,
        private readonly InstallationEnvironmentServiceInterface $environment,
    ) {}

    public function prepare(string $theme, string $adminTheme, string $modules = '', string $blocks = ''): void
    {
        if (file_exists(storage_path('installed')) || is_link(storage_path('installed'))) {
            throw new \DomainException('cms:setup solo prepara aplicaciones nuevas; esta instalación ya está sellada.');
        }
        if (! isset($this->packages->all('dbcms-theme')[$theme])) {
            throw new \DomainException('El tema público seleccionado no está instalado.');
        }
        if (! $this->adminThemes->themeExists($adminTheme)) {
            throw new \DomainException('La apariencia administrativa seleccionada no está instalada.');
        }
        $moduleIds = $this->selection($modules, array_keys($this->packages->modules()));
        $blockIds = $this->selection($blocks, array_keys($this->packages->all('dbcms-block-package')));
        /** @var array{files: array<string, list<string>>, remove: list<string>} $defaults */
        $defaults = json_decode((string) file_get_contents(Cms::path('stubs/laravel-defaults.json')), true, flags: JSON_THROW_ON_ERROR);
        $source = Cms::path('stubs/application');
        $files = [];
        foreach (File::allFiles($source) as $file) {
            $relative = $file->getRelativePathname();
            $files[$relative] = $file->getContents();
        }

        // Validate all destinations before changing any file. A rerun accepts the
        // exact integration files, never silently replaces project modifications.
        foreach ($files as $relative => $contents) {
            $this->assertReplaceable($relative, $contents, $defaults['files'][$relative] ?? []);
        }
        foreach ($defaults['remove'] as $relative) {
            $this->assertReplaceable($relative, null, $defaults['files'][$relative]);
        }
        $this->assertNoLinks('package.json');
        $this->assertNoLinks('.env');
        $package = json_decode((string) File::get(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($package)) {
            throw new \DomainException('package.json no contiene un objeto válido.');
        }
        $assets = json_decode((string) File::get(Cms::path('stubs/assets-package.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach (['scripts', 'dependencies', 'devDependencies'] as $section) {
            $package[$section] = array_replace((array) ($package[$section] ?? []), $assets[$section]);
        }
        $package['private'] = true;
        $package['type'] = 'module';
        $files['package.json'] = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        $this->environment->ensure();
        foreach ($files as $relative => $contents) {
            File::ensureDirectoryExists(dirname(base_path($relative)));
            File::replace(base_path($relative), $contents);
        }
        foreach ($defaults['remove'] as $relative) {
            File::delete(base_path($relative));
        }
        $env = Dotenv::parse(File::get(app()->environmentFilePath()));
        $installToken = $env['CMS_INSTALL_TOKEN'] ?? config('cms.install_token');
        $values = [
            'CMS_INSTALL_TOKEN' => is_string($installToken) && $installToken !== '' ? $installToken : bin2hex(random_bytes(32)),
            'CMS_THEME' => $theme,
            'CMS_BRAND' => $adminTheme,
            'CMS_MODULES' => implode(',', $moduleIds),
            'CMS_BLOCK_PACKAGES' => implode(',', $blockIds),
            'DB_CONNECTION' => 'mysql',
            'SESSION_DRIVER' => 'file',
            'SESSION_ENCRYPT' => 'true',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ];
        if (in_array(trim($env['APP_NAME'] ?? ''), ['', 'Laravel'], true)) {
            $values['APP_NAME'] = 'dbCMS';
        }
        $this->environment->write($values);
    }

    /** @param list<string> $available
     * @return list<string> */
    private function selection(string $value, array $available): array
    {
        $selected = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $identifier) {
            if (! in_array($identifier, $available, true)) {
                throw new \DomainException('La extensión seleccionada no está instalada: '.$identifier);
            }
            $selected[] = $identifier;
        }

        return array_values(array_unique($selected));
    }

    /** @param list<string> $allowedHashes */
    private function assertReplaceable(string $relative, ?string $expected, array $allowedHashes): void
    {
        $this->assertNoLinks($relative);
        $path = base_path($relative);
        if (! file_exists($path)) {
            return;
        }
        if (! is_file($path)) {
            throw new \DomainException('El destino no es un archivo: '.$relative);
        }
        $contents = (string) file_get_contents($path);
        if (($expected !== null && $contents === $expected) || in_array(hash('sha256', $contents), $allowedHashes, true)) {
            return;
        }

        throw new \DomainException('El archivo contiene cambios del proyecto y no se sobrescribirá: '.$relative);
    }

    private function assertNoLinks(string $relative): void
    {
        $path = base_path();
        foreach (explode('/', $relative) as $part) {
            $path .= DIRECTORY_SEPARATOR.$part;
            if (is_link($path)) {
                throw new \DomainException('La preparación no escribe sobre enlaces simbólicos: '.$relative);
            }
        }
    }
}

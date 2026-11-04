<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use Composer\InstalledVersions;
use DbCms\Core\Cms;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Models\CmsModule;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Migrations\Migrator;

/** Migrates installed owners without changing their activation or installation. */
final class MigrateCmsCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cms:migrate {--force : Run migrations in production}';

    protected $description = 'Run core, project and installed module migrations';

    public function handle(Migrator $migrator, ComposerPackages $packages): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        if (! is_file(storage_path('installed'))) {
            $this->error('Complete installation before deploying schema changes.');

            return self::FAILURE;
        }
        $directories = [Cms::path('database/migrations'), Cms::path('src/Content/Pages/Database/Migrations'), database_path('migrations')];
        $passport = InstalledVersions::getInstallPath('laravel/passport');
        if ($passport !== null) {
            $directories[] = $passport.'/database/migrations';
        }
        $available = $packages->modules();
        foreach (CmsModule::query()->where('is_installed', true)->pluck('identifier') as $identifier) {
            $module = $available[$identifier] ?? throw new \DomainException("Installed module [{$identifier}] has no Composer package.");
            $directories[] = $module['directory'].'/Database/Migrations';
        }
        $files = [];
        foreach (array_unique($directories) as $directory) {
            foreach (glob($directory.'/*_*.php') ?: [] as $file) {
                $name = basename($file);
                if (isset($files[$name])) {
                    throw new \DomainException('Duplicate migration identity: '.$name);
                }
                $files[$name] = $file;
            }
        }
        $migrator->run(array_values($files), ['step' => true]);
        $this->info('Installed owners migrated.');

        return self::SUCCESS;
    }
}

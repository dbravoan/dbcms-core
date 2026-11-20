<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Security\AccessProvisioner;
use DbCms\Core\Services\Security\PermissionCatalog;
use Illuminate\Console\Command;

final class SyncAccessCommand extends Command
{
    protected $signature = 'cms:access:sync {--dry-run : Only print the catalogue without writing}';

    protected $description = 'Create or update the CMS permission catalogue and default roles';

    public function handle(AccessProvisioner $provisioner): int
    {
        if ((bool) $this->option('dry-run')) {
            $definitions = PermissionCatalog::definitions(app(ModuleManager::class)->discoverModules());
            $this->table(['Permission', 'Name'], collect($definitions)->map(fn (string $name, string $slug): array => [$slug, $name])->values()->all());
            $this->line('Roles: '.implode(', ', array_keys(PermissionCatalog::roles($definitions))).'.');

            return self::SUCCESS;
        }

        $result = $provisioner->provision();
        $this->info("Access catalogue synchronized: {$result['permissions']} permissions, {$result['roles']} roles, {$result['assignments']} default assignments.");

        return self::SUCCESS;
    }
}

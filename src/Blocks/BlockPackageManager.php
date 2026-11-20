<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Composer\Semver\Semver;
use DbCms\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Manifests are code-owned; persisted state only controls availability. */
final class BlockPackageManager
{
    public function __construct(private readonly ComposerPackages $packages) {}

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $packages = $this->packages->all('dbcms-block-package');
        $dependencies = ['dbravoan/dbcms-core' => 'core'];
        foreach ($this->packages->modules() as $identifier => $module) {
            $dependencies[$module['package']] = $identifier;
        }
        foreach ($packages as &$package) {
            BlockPackageManifest::validate($package);
            $package['requires'] = [];
            foreach ($package['composer_requires'] as $name => $constraint) {
                if (isset($dependencies[$name])) {
                    $package['requires'][$dependencies[$name]] = $constraint;
                }
            }
        }
        unset($package);

        return $packages;
    }

    /** @return array<string,array<string,mixed>> */
    public function forAdministration(): array
    {
        $packages = $this->all();
        $states = $this->availability($packages);
        foreach ($packages as $identifier => &$package) {
            $package['enabled'] = $states[$identifier];
        }
        unset($package);

        return $packages;
    }

    public function isEnabled(string $identifier): bool
    {
        $package = $this->all()[$identifier] ?? null;

        return $package !== null && $this->availability([$identifier => $package])[$identifier];
    }

    /** One snapshot per operation; later operations re-read mutable state.
     * @param  array<string,array<string,mixed>>|null  $packages
     * @return array<string,bool>
     */
    public function availability(?array $packages = null): array
    {
        $packages ??= $this->all();
        $stored = Schema::hasTable('block_packages')
            ? DB::table('block_packages')->pluck('is_enabled', 'identifier')->all()
            : [];
        $states = [];
        $moduleStates = [];
        foreach ($packages as $identifier => $package) {
            $states[$identifier] = (bool) ($stored[$identifier] ?? false) && $this->unmetRequirement($package, $moduleStates) === null;
        }

        return $states;
    }

    public function setEnabled(string $identifier, bool $enabled): void
    {
        $package = $this->all()[$identifier] ?? null;
        if ($package === null) {
            $this->invalid('Block package not found.');
        }
        if ($enabled) {
            $unmet = $this->unmetRequirement($package);
            if ($unmet !== null) {
                $this->invalid('Missing or incompatible requirement: '.$unmet);
            }
        }
        $wasEnabled = $this->isEnabled($identifier);
        DB::transaction(static function () use ($identifier, $enabled, $wasEnabled): void {
            DB::table('block_packages')->updateOrInsert(['identifier' => $identifier], ['is_enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()]);
            if ($wasEnabled !== $enabled) {
                event(new BlockPackageAvailabilityChanged($identifier, $enabled));
            }
        });
        app(PageCacheService::class)->invalidate();
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package' => $message]);
    }

    /** @param array<string,mixed> $package
     * @param  array<array-key,bool>  $moduleStates
     */
    private function unmetRequirement(array $package, array &$moduleStates = []): ?string
    {
        foreach ((array) ($package['requires'] ?? []) as $identifier => $constraint) {
            $manifest = $identifier === 'core'
                ? ['version' => (string) config('cms.version')]
                : app(ModuleManager::class)->getModuleManifest((string) $identifier);
            if ($manifest === null || ! Semver::satisfies((string) $manifest['version'], (string) $constraint)
                || ($identifier !== 'core' && ! ($moduleStates[$identifier] ??= app(ModuleManager::class)->isModuleEnabled((string) $identifier)))) {
                return (string) $identifier;
            }
        }

        return null;
    }
}

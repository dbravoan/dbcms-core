<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Events\ModuleAvailabilityChanged;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Search\SearchIndexer;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ModuleManager
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $manifests = null;

    /** @var array<string, ModuleInterface> */
    protected array $registeredModules = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Reads manifests without creating directories or changing persisted state.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discoverModules(): array
    {
        $manifests = $this->app->make(ComposerPackages::class)->modules();

        return $this->manifests = (new ModuleManifestGraph($manifests, (string) config('cms.version')))->manifests();
    }

    /**
     * Registers the selected composition before Laravel boots its providers.
     */
    public function registerConfiguredModules(): void
    {
        $discovered = $this->discoverModules();
        try {
            $records = Schema::hasTable('cms_modules')
                ? DB::table('cms_modules')->where('is_installed', true)->get()->keyBy('identifier')
                : collect();
        } catch (QueryException) {
            // The installer and Composer discovery also boot before a database exists.
            $records = collect();
        }
        $selected = array_values(array_unique(array_merge($this->configuredModules(), $records->keys()->all())));
        $graph = new ModuleManifestGraph($discovered, (string) config('cms.version'));
        foreach ($graph->ordered($selected) as $identifier) {
            $moduleRecord = $records->get($identifier);
            if ($moduleRecord !== null) {
                $settings = json_decode((string) ($moduleRecord->settings ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
                foreach (is_array($settings) ? $settings : [] as $key => $value) {
                    config(["modules.{$identifier}.{$key}" => $value]);
                }
            }
            $this->registerModule($identifier, $discovered[$identifier]);
        }
    }

    /** @param list<string>|null $identifiers */
    public function reconcileModules(?array $identifiers = null): void
    {
        $manifests = $this->discoverModules();
        $selected = $identifiers ?? array_keys($manifests);
        foreach ($selected as $identifier) {
            if (! isset($manifests[$identifier])) {
                throw new \DomainException("Selected module [{$identifier}] is unavailable.");
            }
        }
        DB::transaction(function () use ($selected, $manifests): void {
            foreach ($selected as $identifier) {
                $manifest = $manifests[$identifier];
                $module = CmsModule::firstOrNew(['identifier' => $identifier]);
                if (! $module->exists) {
                    $module->fill(['is_installed' => false, 'is_active' => false]);
                }
                $module->fill([
                    'name' => $manifest['name'], 'version' => $manifest['version'],
                    'description' => $manifest['description'] ?? '', 'provider' => $manifest['provider'],
                    'is_pageable' => (bool) ($manifest['is_pageable'] ?? false),
                ])->save();
            }
        });
    }

    /** @return Collection<int, CmsModule> */
    public function catalog(): Collection
    {
        $stored = CmsModule::query()->get()->keyBy('identifier');

        return collect($this->discoverModules())->map(function (array $manifest, string $identifier) use ($stored): CmsModule {
            $module = $stored->get($identifier) ?? new CmsModule(['identifier' => $identifier, 'is_active' => false, 'is_installed' => false]);
            $module->fill(array_intersect_key($manifest, array_flip(['name', 'version', 'description', 'provider', 'is_pageable'])));

            return $module;
        })->values();
    }

    public function toggle(string $identifier): bool
    {
        $module = CmsModule::where('identifier', $identifier)->firstOrFail();
        if (! $module->is_installed) {
            throw new \DomainException('Install the module before activating it.');
        }
        $active = ! $module->is_active;
        $active ? $this->activate($identifier) : $this->deactivate($identifier);

        return $active;
    }

    /** Explicit installation entry point used by the installer and fixtures. */
    public function installConfiguredModules(): void
    {
        $graph = new ModuleManifestGraph($this->discoverModules(), (string) config('cms.version'));
        $selected = $graph->ordered($this->configuredModules());
        $this->reconcileModules($selected);
        foreach ($selected as $identifier) {
            $this->install($identifier);
        }
    }

    /** @return list<string> */
    private function configuredModules(): array
    {
        $selected = config('cms.modules', []);
        if (! is_array($selected) || ! array_is_list($selected) || array_filter($selected, static fn (mixed $identifier): bool => ! is_string($identifier)) !== []) {
            throw new \DomainException('cms.modules must be a list of module identifiers.');
        }

        return $selected;
    }

    /** @param array<string, mixed> $manifest */
    private function registerModule(string $identifier, array $manifest): void
    {
        if (! isset($this->registeredModules[$identifier])) {
            $providerClass = $this->providerClass($identifier, $manifest);
            $provider = $this->app->register($providerClass);
            if (! $provider instanceof ModuleInterface) {
                throw new \DomainException("Module [{$identifier}] does not implement its lifecycle contract.");
            }
            $this->registeredModules[$identifier] = $provider;
        }
    }

    /** @param array<string, mixed> $manifest
     * @return class-string<BaseModuleServiceProvider>
     */
    private function providerClass(string $identifier, array $manifest): string
    {
        $provider = $manifest['provider'] ?? null;
        if (! is_string($provider) || ! is_subclass_of($provider, BaseModuleServiceProvider::class)) {
            throw new \DomainException("Module [{$identifier}] has no valid service provider.");
        }

        return $provider;
    }

    private function lockModuleStates(): void
    {
        CmsModule::query()->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** @return list<string> */
    private function activeIdentifiers(): array
    {
        return array_values(CmsModule::query()->where('is_installed', true)->where('is_active', true)->pluck('identifier')->map(static fn ($identifier): string => (string) $identifier)->filter(fn (string $identifier): bool => $this->isModuleEnabled($identifier))->all());
    }

    /**
     * Settings that control module availability obey the same locked dependency graph.
     *
     * @param  array<string, mixed>  $settings
     */
    public function assertAvailabilitySettings(string $identifier, array $settings): void
    {
        $key = $this->getModuleManifest($identifier)['availability_setting'] ?? null;
        if (! is_string($key) || ! array_key_exists($key, $settings)) {
            return;
        }
        $this->lockModuleStates();
        if (! filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN)) {
            $this->assertNoActiveDependents($identifier);
        } elseif (CmsModule::query()->where('identifier', $identifier)->where('is_installed', true)->where('is_active', true)->exists()) {
            $this->assertRequirements($identifier);
        }
    }

    /** Synchronize projections inside the settings write transaction. */
    public function synchronizeAvailabilitySettings(string $identifier, bool $previouslyAvailable): bool
    {
        if ($previouslyAvailable === $this->isModuleEnabled($identifier)) {
            return false;
        }

        $this->rebuildSearchIndex();
        $this->notifyAvailabilityAfterCommit($identifier, 'settings');

        return true;
    }

    private function assertRequirements(string $identifier): void
    {
        $graph = new ModuleManifestGraph($this->discoverModules(), (string) config('cms.version'));
        $graph->ordered(array_values(array_unique([...$this->activeIdentifiers(), $identifier])));
    }

    private function assertNoActiveDependents(string $identifier): void
    {
        $graph = new ModuleManifestGraph($this->discoverModules(), (string) config('cms.version'));
        $dependents = $graph->dependents($identifier, $this->activeIdentifiers());
        if ($dependents !== []) {
            throw new \DomainException("Module [{$identifier}] is required by active modules: ".implode(', ', $dependents).'.');
        }
    }

    private function assertNoInstalledDependents(string $identifier): void
    {
        $installed = array_values(CmsModule::query()->where('is_installed', true)->pluck('identifier')->map(static fn ($id): string => (string) $id)->all());
        $graph = new ModuleManifestGraph($this->discoverModules(), (string) config('cms.version'));
        $dependents = $graph->dependents($identifier, $installed);
        if ($dependents !== []) {
            throw new \DomainException("Module [{$identifier}] is required by installed modules: ".implode(', ', $dependents).'.');
        }
    }

    /**
     * Installs a module by running its migrations.
     */
    public function install(string $identifier): bool
    {
        $this->assertRequirements($identifier);
        $migrationPath = $this->migrationPath($identifier);
        $this->reconcileModules([$identifier]);

        if (File::isDirectory($migrationPath)) {
            $exitCode = Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => true,
            ]);
            if ($exitCode !== 0) {
                throw new \RuntimeException('Module migrations failed; installation state was not changed.');
            }
        }

        DB::transaction(function () use ($identifier): void {
            $this->lockModuleStates();
            $this->assertRequirements($identifier);
            CmsModule::where('identifier', $identifier)->update(['is_installed' => true, 'is_active' => true]);
            $this->registerModule($identifier, $this->getModuleManifest($identifier) ?? []);
            $this->rebuildSearchIndex();
        });
        app(PageCacheService::class)->invalidate();

        $this->notifyAvailabilityAfterCommit($identifier, 'install');

        return true;
    }

    /**
     * Uninstalls availability while preserving migrations and all user data.
     */
    public function uninstall(string $identifier): bool
    {
        DB::transaction(function () use ($identifier): void {
            $this->lockModuleStates();
            $this->assertNoInstalledDependents($identifier);
            CmsModule::where('identifier', $identifier)->firstOrFail()->update(['is_installed' => false, 'is_active' => false]);
            $this->rebuildSearchIndex();
        });
        app(PageCacheService::class)->invalidate();

        $this->notifyAvailabilityAfterCommit($identifier, 'uninstall');

        return true;
    }

    /**
     * Activates a module.
     */
    public function activate(string $identifier): bool
    {
        $updated = DB::transaction(function () use ($identifier): bool {
            $this->lockModuleStates();
            if (! CmsModule::where('identifier', $identifier)->where('is_installed', true)->exists()) {
                return false;
            }
            $this->assertRequirements($identifier);

            $updated = (bool) CmsModule::where('identifier', $identifier)->update(['is_active' => true]);
            if ($updated) {
                $this->rebuildSearchIndex();
            }

            return $updated;
        });
        app(PageCacheService::class)->invalidate();

        if ($updated) {
            $this->notifyAvailabilityAfterCommit($identifier, 'activate');
        }

        return $updated;
    }

    /**
     * Deactivates a module.
     */
    public function deactivate(string $identifier): bool
    {
        $updated = DB::transaction(function () use ($identifier): bool {
            $this->lockModuleStates();
            $this->assertNoActiveDependents($identifier);

            $updated = (bool) CmsModule::where('identifier', $identifier)->update(['is_active' => false]);
            if ($updated) {
                $this->rebuildSearchIndex();
            }

            return $updated;
        });
        app(PageCacheService::class)->invalidate();

        if ($updated) {
            $this->notifyAvailabilityAfterCommit($identifier, 'deactivate');
        }

        return $updated;
    }

    private function notifyAvailabilityAfterCommit(string $identifier, string $operation): void
    {
        $event = new ModuleAvailabilityChanged($identifier, $this->isModuleEnabled($identifier), $operation);
        DB::afterCommit(static fn () => event($event));
    }

    private function rebuildSearchIndex(): void
    {
        if ($this->app->bound(SearchIndexer::class) && Schema::hasTable('search_documents')) {
            $this->app->make(SearchIndexer::class)->indexAll();
        }
    }

    /** @return array<string, ModuleInterface> */
    public function getActiveModules(): array
    {
        return array_filter($this->registeredModules, fn (ModuleInterface $provider, string $identifier): bool => $this->isModuleEnabled($identifier), ARRAY_FILTER_USE_BOTH);
    }

    /** Resolve ownership from registered manifests, including custom identifiers. */
    public function ownerOfClass(string $class): ?string
    {
        foreach ($this->registeredModules as $identifier => $provider) {
            $namespace = (new \ReflectionClass($provider))->getNamespaceName();
            if ($namespace !== '' && str_starts_with($class, $namespace.'\\')) {
                return $identifier;
            }
        }

        return null;
    }

    /**
     * Checks if a module is currently active and enabled.
     */
    public function isModuleEnabled(string $identifier): bool
    {
        if ($this->getModuleManifest($identifier) === null) {
            return false;
        }
        try {
            $record = CmsModule::where('identifier', $identifier)->first();
            if ($record !== null) {
                if (! (bool) $record->is_active || ! (bool) $record->is_installed) {
                    return false;
                }

                $availabilitySetting = $this->getModuleManifest($identifier)['availability_setting'] ?? null;
                if (is_string($availabilitySetting) && is_array($record->settings) && array_key_exists($availabilitySetting, $record->settings)) {
                    $enabled = $record->settings[$availabilitySetting];
                    if ($enabled === false || $enabled === 0 || $enabled === '0' || $enabled === 'false') {
                        return false;
                    }
                }

                return true;
            }
        } catch (QueryException) {
            // Availability fails closed before installation or during a DB outage.
        }

        return false;
    }

    /** Resolve case-sensitive module paths from the provider, never its identifier. */
    private function migrationPath(string $identifier): string
    {
        $provider = $this->providerClass($identifier, $this->getModuleManifest($identifier) ?? []);
        $file = (new \ReflectionClass($provider))->getFileName();
        $directory = $file === false ? false : realpath(dirname($file));
        $package = $this->getModuleManifest($identifier);
        $root = is_array($package) ? realpath((string) $package['directory']) : false;
        if ($directory === false || $root === false || ($directory !== $root && ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Module provider is outside its Composer package.');
        }

        return $directory.'/Database/Migrations';
    }

    /**
     * Checks if a module manifest has been discovered on disk.
     */
    public function isModuleDiscovered(string $identifier): bool
    {
        return $this->getModuleManifest($identifier) !== null;
    }

    /**
     * Retrieves the parsed module.json manifest for a given module identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getModuleManifest(string $identifier): ?array
    {
        if ($this->manifests === null) {
            $this->discoverModules();
        }

        return $this->manifests[$identifier] ?? null;
    }

    /**
     * Assesses the impact of deactivating a module (affected menus, pages, and core risks).
     *
     * @return array{
     *     identifier: string,
     *     dependents: list<string>,
     *     warning: ?string,
     *     affected_menus: array<int, array{menu: string, label: string, url: string}>,
     *     affected_pages: array<int, array{id: int, title: string, reason: string}>,
     *     has_impact: bool
     * }
     */
    public function getDeactivationImpact(string $identifier): array
    {
        $identifier = strtolower($identifier);
        $graph = new ModuleManifestGraph($this->discoverModules(), (string) config('cms.version'));
        $dependents = $graph->dependents($identifier, $this->activeIdentifiers());
        $warning = $dependents === [] ? null : 'Desactiva antes los módulos dependientes: '.implode(', ', $dependents).'.';

        $affectedMenus = [];
        $affectedPages = [];

        $schemas = app(EntitySchemaRegistryInterface::class)->all();
        $types = [];
        $sourceTypes = [];
        $prefixes = [];
        $localization = app(LocalizationServiceInterface::class);
        foreach ($schemas as $schema) {
            if (($schema->ownerModule ?? $this->ownerOfClass($schema->model)) === $identifier) {
                $types[] = $schema->type;
                if ($schema->publicContent?->blockSource !== null) {
                    $sourceTypes[] = $schema->type;
                }
                if ($schema->urlPrefix !== null && $schema->urlPrefix !== '') {
                    array_push($prefixes, ...$localization->getAllModuleSlugs($schema->urlPrefix));
                }
            }
        }
        foreach (MenuItem::with(['menu', 'translations'])->get() as $item) {
            $segments = explode('/', trim((string) parse_url((string) $item->url, PHP_URL_PATH), '/'));
            if (in_array((string) $item->type, $types, true) || array_intersect($segments, $prefixes) !== []) {
                $affectedMenus[] = ['menu' => (string) ($item->menu->name ?? 'Menú'), 'label' => (string) ($item->translations->first()->title ?? $item->url), 'url' => (string) $item->url];
            }
        }

        $blockTypes = [];
        foreach (app(BlockRegistryInterface::class)->all() as $type => $block) {
            if ($block->getRequiredModule() === $identifier) {
                $blockTypes[] = $type;
            }
        }
        if ($blockTypes !== [] || $sourceTypes !== []) {
            foreach (PageSection::with(['page.translations', 'translations'])->get() as $section) {
                $master = array_replace(['type' => $section->type], $section->styling_json ?? []);
                $payloads = [$master];
                foreach ($section->translations as $translation) {
                    $payloads[] = SectionLayout::resolve($master, $translation->content_json ?? []);
                }
                foreach ($payloads as $payload) {
                    $type = $this->findAffectedBlock($payload, $blockTypes, $sourceTypes);
                    if ($type !== null) {
                        $affectedPages[] = ['id' => (int) $section->page_id, 'title' => (string) ($section->page->translations->first()->title ?? 'Página #'.$section->page_id), 'reason' => 'Contiene bloque: '.$type];
                        break;
                    }
                }
            }
        }

        // Deduplicate affected pages by ID
        $uniquePages = [];
        $seenPageIds = [];
        foreach ($affectedPages as $p) {
            if (! in_array($p['id'], $seenPageIds, true)) {
                $seenPageIds[] = $p['id'];
                $uniquePages[] = $p;
            }
        }

        $hasImpact = $dependents !== [] || count($affectedMenus) > 0 || count($uniquePages) > 0;

        return [
            'identifier' => $identifier,
            'dependents' => $dependents,
            'warning' => $warning,
            'affected_menus' => $affectedMenus,
            'affected_pages' => $uniquePages,
            'has_impact' => $hasImpact,
        ];
    }

    /** @param array<string, mixed> $payload
     * @param  list<string>  $types
     * @param  list<string>  $sourceTypes
     */
    private function findAffectedBlock(array $payload, array $types, array $sourceTypes = []): ?string
    {
        foreach (app(BlockTree::class)->nodes($payload) as $block) {
            $type = (string) ($block['type'] ?? 'text');
            if (in_array($type, $types, true) || ($type === 'collection' && in_array($block['source'] ?? null, $sourceTypes, true))) {
                return $type;
            }
            foreach ((array) ($block['bindings'] ?? []) as $binding) {
                if (is_array($binding) && in_array($binding['source'] ?? null, $sourceTypes, true)) {
                    return $type;
                }
            }
        }

        return null;
    }
}

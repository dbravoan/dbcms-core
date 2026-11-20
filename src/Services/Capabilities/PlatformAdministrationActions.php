<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\Tools\Entity\EntityTypesTool;
use DbCms\Core\Capabilities\Tools\Resource\ResourceTypesTool;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Setting;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\SiteSettingsService;
use DbCms\Core\Services\Cms\ThemeManagerService;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformAdministrationActions
{
    public function __construct(private readonly ModuleManager $modules, private readonly ModuleSettingsServiceInterface $moduleSettings, private readonly ThemeManagerService $themes, private readonly SiteSettingsService $settings, private readonly ExternalStorageService $storage, private readonly CapabilityAuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $actor = app(ExecutionContext::class)->actor;

        return [
            'tools' => $actor === null ? [] : app(AvailableCapabilities::class)->manifest($actor),
            'entity_types' => app(EntityTypesTool::class)->handle([])['types'],
            'resources' => app(ResourceTypesTool::class)->handle([])['resources'],
            'abilities' => [
                'read' => 'requires mcp:read',
                'write' => 'requires mcp:write',
                'destructive' => 'requires mcp:destructive and a human approval of this change in the backoffice',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function moduleToggle(array $args): array
    {
        $identifier = (string) ($args['identifier'] ?? '');
        $module = CmsModule::query()->where('identifier', $identifier)->first();

        if (! $module instanceof CmsModule) {
            throw ValidationException::withMessages(['identifier' => "Unknown module [{$identifier}]."]);
        }

        $enabled = array_key_exists('enabled', $args)
            ? filter_var($args['enabled'], FILTER_VALIDATE_BOOLEAN)
            : ! $module->is_active;

        if ($enabled) {
            $this->modules->activate($identifier);
        } else {
            $this->modules->deactivate($identifier);
        }

        return [
            'identifier' => $identifier,
            'enabled' => $this->modules->isModuleEnabled($identifier),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleInstall(string $identifier): array
    {
        $installed = $this->modules->install($identifier);

        if (! $installed) {
            throw ValidationException::withMessages(['identifier' => "Module [{$identifier}] could not be installed."]);
        }

        return ['identifier' => $identifier, 'installed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleUninstall(string $identifier): array
    {
        $uninstalled = $this->modules->uninstall($identifier);

        if (! $uninstalled) {
            throw ValidationException::withMessages(['identifier' => "Module [{$identifier}] could not be uninstalled."]);
        }

        return ['identifier' => $identifier, 'uninstalled' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function moduleSettings(string $identifier, array $settings): array
    {
        if (CmsModule::query()->where('identifier', $identifier)->doesntExist()) {
            throw ValidationException::withMessages(['identifier' => "Unknown module [{$identifier}]."]);
        }

        $this->moduleSettings->setMany($identifier, $settings);

        return [
            'identifier' => $identifier,
            'settings' => $this->auditLogger->maskModuleSettings($identifier, $this->moduleSettings->getAll($identifier), '••••••••'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function themeActivate(string $theme): array
    {
        $theme = Str::slug($theme);

        if (! isset(app(ThemeManagerService::class)->getAvailableThemes()[$theme])) {
            throw ValidationException::withMessages(['theme' => "Unknown theme [{$theme}]."]);
        }

        $this->themes->setActiveTheme($theme);

        return ['theme' => $theme, 'activated' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsGet(): array
    {
        $settings = Setting::query()
            ->get()
            ->mapWithKeys(static function (Setting $setting): array {
                $key = (string) $setting->getAttribute('key');

                return [$key => $setting->getAttribute('value')];
            })
            ->all();

        return [
            'settings' => $this->auditLogger->redact($settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function settingsUpdate(array $attributes): array
    {
        if ($attributes === []) {
            throw ValidationException::withMessages(['attributes' => 'Nothing to update.']);
        }

        $this->settings->update($attributes);

        return ['updated' => array_keys($attributes)];
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionGet(): array
    {
        return [
            'enabled' => $this->storage->isEnabled(),
            'configured' => $this->storage->isConfigured(),
            'provider' => $this->storage->provider(),
            'providers' => $this->storage->providerLabels(),
            'disk' => $this->storage->diskName(),
            'configuration' => $this->auditLogger->redact($this->storage->configuration()),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function connectionUpdate(array $attributes): array
    {
        if ($attributes === []) {
            throw ValidationException::withMessages(['attributes' => 'Nothing to update.']);
        }

        $this->storage->save($attributes);

        return $this->connectionGet();
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionTest(): array
    {
        return $this->storage->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function cachePurge(): array
    {
        Artisan::call('cms:cache:purge');

        return ['purged' => true, 'output' => trim(Artisan::output())];
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Modules\ModuleSettingsExtensionRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ModuleSettingsService implements ModuleSettingsServiceInterface
{
    /**
     * In-memory cache of resolved settings per module.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $cache = [];

    public function __construct(
        protected Application $app,
        protected ModuleManager $moduleManager,
        protected LocalizationServiceInterface $localization
    ) {}

    /**
     * Get a setting value for a specific module, falling back to config or default.
     */
    public function get(string $moduleIdentifier, string $key, mixed $default = null): mixed
    {
        $all = $this->getAll($moduleIdentifier);

        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        // Check runtime config fallback
        $configVal = Config::get("modules.{$moduleIdentifier}.{$key}");
        if ($configVal !== null && $configVal !== '') {
            return $configVal;
        }

        // Check schema default
        $schema = $this->getSchema($moduleIdentifier);
        if (isset($schema[$key]['default'])) {
            return $schema[$key]['default'];
        }

        return $default;
    }

    /**
     * Raw stored settings of a module (bypasses the schema), used to keep the
     * slugs of deactivated languages resolvable.
     *
     * @return array<string, mixed>
     */
    public function storedRaw(string $moduleIdentifier): array
    {
        try {
            /** @var CmsModule|null $module */
            $module = CmsModule::where('identifier', $moduleIdentifier)->first();

            return (array) ($module->settings ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get all stored settings for a module with decrypted secrets.
     *
     * @return array<string, mixed>
     */
    public function getAll(string $moduleIdentifier): array
    {
        $cacheable = DB::transactionLevel() === 0;
        if ($cacheable && isset($this->cache[$moduleIdentifier])) {
            return $this->cache[$moduleIdentifier];
        }

        try {
            /** @var CmsModule|null $module */
            $module = CmsModule::where('identifier', $moduleIdentifier)->first();
            $stored = (array) ($module->settings ?? []);
        } catch (\Throwable) {
            $stored = [];
        }
        $schema = $this->getSchema($moduleIdentifier);

        $resolved = [];

        foreach ($schema as $key => $fieldDef) {
            if (! is_array($fieldDef)) {
                continue;
            }

            $isSecret = (bool) ($fieldDef['secret'] ?? false);
            $type = (string) ($fieldDef['type'] ?? 'text');
            $default = $fieldDef['default'] ?? null;

            if (array_key_exists($key, $stored)) {
                $val = $stored[$key];

                if ($isSecret && is_string($val) && ! empty($val)) {
                    try {
                        $val = Crypt::decryptString($val);
                    } catch (\Throwable) {
                        // Invalid ciphertext is never accepted as a usable secret.
                        $val = null;
                    }
                }

                if ($type === 'boolean') {
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                } elseif ($type === 'number' && is_numeric($val)) {
                    $val = str_contains((string) $val, '.') ? (float) $val : (int) $val;
                }

                $resolved[$key] = $val;
            } else {
                $resolved[$key] = $default;
            }
        }

        // Include any additional arbitrary stored keys not explicitly in schema
        foreach ($stored as $k => $v) {
            if (! array_key_exists($k, $resolved)) {
                $resolved[$k] = $v;
            }
        }

        if ($cacheable) {
            $this->cache[$moduleIdentifier] = $resolved;
        }

        return $resolved;
    }

    /**
     * Set a single setting value for a module.
     */
    public function set(string $moduleIdentifier, string $key, mixed $value): void
    {
        $current = $this->getAll($moduleIdentifier);
        $current[$key] = $value;
        $this->setMany($moduleIdentifier, $current);
    }

    /**
     * Mass update settings for a module, preserving unedited secret keys.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setMany(string $moduleIdentifier, array $settings): void
    {
        DB::transaction(function () use ($moduleIdentifier, $settings): void {
            $this->moduleManager->assertAvailabilitySettings($moduleIdentifier, $settings);
            $previouslyAvailable = $this->moduleManager->isModuleEnabled($moduleIdentifier);
            /** @var CmsModule $module */
            $module = CmsModule::where('identifier', $moduleIdentifier)->lockForUpdate()->firstOrFail();
            unset($this->cache[$moduleIdentifier]);
            $currentStored = (array) ($module->settings ?? []);
            $schema = $this->getSchema($moduleIdentifier);
            $settings = $this->app->make(ModuleSettingsExtensionRegistry::class)->normalize($moduleIdentifier, $settings);

            $toPersist = $currentStored;

            foreach ($schema as $key => $fieldDef) {
                if (! array_key_exists($key, $settings)) {
                    continue;
                }

                if (! is_array($fieldDef)) {
                    $toPersist[$key] = $settings[$key];

                    continue;
                }

                $isSecret = (bool) ($fieldDef['secret'] ?? false);
                $type = (string) ($fieldDef['type'] ?? 'text');

                if ($type === 'boolean') {
                    $toPersist[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);

                    continue;
                }

                $inputVal = $settings[$key];

                if ($isSecret) {
                    // If left empty or whitespace on update, keep existing encrypted value
                    if ($inputVal === null || (is_string($inputVal) && (trim($inputVal) === '' || str_starts_with($inputVal, '••••')))) {
                        continue;
                    }
                    if (! is_string($inputVal)) {
                        throw new \InvalidArgumentException('Secret settings must be strings.');
                    }
                    $toPersist[$key] = Crypt::encryptString($inputVal);
                } else {
                    $toPersist[$key] = $inputVal;
                }
            }

            $module->update(['settings' => $toPersist]);
            $availabilityChanged = $this->moduleManager->synchronizeAvailabilitySettings($moduleIdentifier, $previouslyAvailable);

            DB::afterCommit(function () use ($moduleIdentifier, $availabilityChanged): void {
                // Settings shape public output (sitemaps, llms.txt, module toggles…), so
                // the cached pages must be rebuilt with the new values.
                $this->app->make(PageCacheService::class)->invalidate();

                // Invalidate memory cache and update runtime config
                unset($this->cache[$moduleIdentifier]);
                $all = $this->getAll($moduleIdentifier);

                foreach ($all as $k => $v) {
                    Config::set("modules.{$moduleIdentifier}.{$k}", $v);
                }
                if (! $availabilityChanged) {
                    Artisan::call('queue:restart');
                }
            });
        });
    }

    /**
     * Get the schema definition for a module's settings.
     *
     * Locale/market aware: a key written as `slug_{locale}` (or
     * `slug_{market}`) is expanded, **at read time**, into one concrete field
     * per active language/market, so the settings screen offers every language
     * created in the backoffice and the PUT controller can persist it. The
     * expansion happens in this single access point, so the view, the
     * validation rules, `setMany()` and `isConfigured()` all inherit it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchema(string $moduleIdentifier): array
    {
        $schema = $this->rawSchema($moduleIdentifier);

        if ($schema === [] || ! $this->hasPlaceholders($schema)) {
            return $schema;
        }

        return $this->expandPlaceholders($schema);
    }

    /**
     * The manifest schema exactly as declared (no placeholder expansion).
     *
     * @return array<string, array<string, mixed>>
     */
    private function rawSchema(string $moduleIdentifier): array
    {
        // 1. Try from active module instance. A module may register only a
        //    partial schema (e.g. Shop declares its two dynamic fields), so the
        //    manifest is merged in as the base — never the other way around, or
        //    declared fields would silently disappear.
        $manifest = $this->moduleManager->getModuleManifest($moduleIdentifier);
        $fromManifest = ($manifest !== null && isset($manifest['settings']) && is_array($manifest['settings']))
            ? $manifest['settings']
            : [];

        $active = $this->moduleManager->getActiveModules();
        if (isset($active[$moduleIdentifier])) {
            $fromInstance = $active[$moduleIdentifier]->getSettingsSchema();

            return $this->sanitizeSchema(array_replace($fromManifest, $fromInstance));
        }

        return $this->sanitizeSchema($fromManifest);
    }

    /**
     * Drops schema entries that are not a field definition, so a misconfigured
     * module can never break the whole settings screen (or a save).
     *
     * @param  array<mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function sanitizeSchema(array $schema): array
    {
        $clean = [];

        foreach ($schema as $key => $field) {
            if (is_string($key) && is_array($field)) {
                $clean[$key] = $field;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, array<string, mixed>>  $schema
     */
    private function hasPlaceholders(array $schema): bool
    {
        foreach (array_keys($schema) as $key) {
            if (str_contains((string) $key, '{locale}') || str_contains((string) $key, '{market}')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expands `{locale}`/`{market}` placeholders into concrete keys.
     *
     * The value already stored in the database keeps resolving because the
     * concrete keys (`slug_es`, `slug_fr`…) are exactly the ones the rest of
     * the application reads (`LocalizationService::getModulePrefix()`), and the
     * per-request memo avoids re-querying the active languages per field.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function expandPlaceholders(array $schema): array
    {
        $languages = $this->localization->activeLanguages();
        $markets = $this->localization->activeMarkets();
        $expanded = [];

        foreach ($schema as $key => $field) {
            if (! str_contains($key, '{locale}') && ! str_contains($key, '{market}')) {
                $expanded[$key] = $field;

                continue;
            }

            if (str_contains($key, '{locale}')) {
                foreach ($languages as $language) {
                    $expanded[str_replace('{locale}', $language->code, $key)] = $this->templated($field, [
                        '{locale}' => strtoupper($language->code),
                        '{language}' => (string) ($language->native_name ?: $language->name),
                    ], 'locale', (string) $language->code);
                }

                continue;
            }

            foreach ($markets as $market) {
                $expanded[str_replace('{market}', $market->code, $key)] = $this->templated($field, [
                    '{market}' => strtoupper((string) $market->code),
                    '{market_name}' => (string) $market->name,
                ], 'market', (string) $market->code);
            }
        }

        return $expanded;
    }

    /**
     * Applies the placeholder replacements to a field definition and stamps the
     * scope so the view knows it belongs to a language/market group.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>
     */
    private function templated(array $field, array $replacements, string $scope, string $code): array
    {
        // Per-language (or per-market) suggestions: a `defaults` map keeps the
        // natural slug of each language (`tienda`, `botiga`, `boutique`…) while
        // the single `default` stays as the last resort.
        if (isset($field['defaults']) && is_array($field['defaults']) && array_key_exists($code, $field['defaults'])) {
            $field['default'] = $field['defaults'][$code];
        }

        unset($field['defaults']);

        foreach (['label', 'description', 'placeholder'] as $textKey) {
            if (isset($field[$textKey]) && is_string($field[$textKey])) {
                $field[$textKey] = str_replace(array_keys($replacements), array_values($replacements), $field[$textKey]);
            }
        }

        $field['scope'] = $scope;
        $field[$scope] = $code;

        return $field;
    }

    /**
     * Check if a module has all its required settings configured.
     */
    public function isConfigured(string $moduleIdentifier): bool
    {
        $schema = $this->getSchema($moduleIdentifier);
        if (empty($schema)) {
            return true;
        }

        $all = $this->getAll($moduleIdentifier);

        foreach ($schema as $key => $fieldDef) {
            $isRequired = (bool) ($fieldDef['required'] ?? false);
            if ($isRequired) {
                $val = $all[$key] ?? null;
                if ($val === null || $val === '') {
                    return false;
                }
            }
        }

        return true;
    }
}

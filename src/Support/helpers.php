<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\DTOs\BrandIdentity;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Forms\FieldTypeRegistry;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Cms\ThemeManagerService;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('cms_asset_entries')) {
    /** @return list<string> Shared runtime plus the selected brand and public theme. */
    function cms_asset_entries(bool $frontend = false): array
    {
        $entries = ['resources/css/app.css', 'resources/js/app.js'];
        $catalog = app(ComposerPackages::class);
        $selected = [$catalog->all('dbcms-admin-theme')[brand()->theme] ?? null];
        if ($frontend) {
            $theme = app(ThemeManagerService::class)->getActiveTheme();
            $selected[] = $catalog->all('dbcms-theme')[$theme] ?? null;
        }
        foreach ($selected as $package) {
            if (is_array($package) && is_file($package['directory'].'/theme.css')) {
                $entries[] = 'vendor/'.$package['package'].'/theme.css';
            }
        }

        return $entries;
    }
}

if (! function_exists('theme_asset')) {
    /**
     * Resolve the URL of an asset shipped inside a backoffice theme's assets/ dir.
     *
     * `theme_asset('termocms.css')` uses the active theme; pass an explicit theme
     * id as the second argument to target another one.
     */
    function theme_asset(string $path, ?string $theme = null): string
    {
        if (! app()->bound(AdminThemeServiceInterface::class)) {
            return asset('themes/admin/'.($theme ?? 'dbcms').'/'.ltrim($path, '/'));
        }

        /** @var AdminThemeServiceInterface $themes */
        $themes = app(AdminThemeServiceInterface::class);

        return $themes->themeAsset($theme ?? $themes->getActiveTheme(), $path);
    }
}

if (! function_exists('brand')) {
    /**
     * Active backoffice brand identity (from the active theme manifest).
     *
     * Single accessor for brand values — never hardcode a brand in views/config.
     *
     * @see docs/EXTENSIONS.md
     */
    function brand(): BrandIdentity
    {
        return app(BrandServiceInterface::class)->current();
    }
}

if (! function_exists('module_setting')) {
    /**
     * Get a setting value for a specific CMS module with optional fallback.
     */
    function module_setting(string $module, string $key, mixed $default = null): mixed
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            return app(ModuleSettingsServiceInterface::class)->get($module, $key, $default);
        }

        return config("modules.{$module}.{$key}", $default);
    }
}

if (! function_exists('setting')) {
    /**
     * Get a general CMS site setting value with optional fallback.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        if (app()->bound(GeneralSettingsServiceInterface::class)) {
            return app(GeneralSettingsServiceInterface::class)->get($key, $default);
        }

        return $default;
    }
}

if (! function_exists('public_translation')) {
    /**
     * Resolve the public translation for the active locale and market.
     *
     * Never falls back to another language: a missing translation returns null
     * so the caller can render a 404 or hide the entity.
     */
    function public_translation(?Model $entity, ?string $locale = null, ?Market $market = null): ?Model
    {
        if ($entity === null) {
            return null;
        }

        $locale ??= app()->getLocale();

        if ($market === null && app()->bound('current.market')) {
            $current = app('current.market');
            $market = $current instanceof Market ? $current : null;
        }

        return PublicTranslation::resolve($entity, $locale, $market);
    }
}

if (! function_exists('captcha_available')) {
    /**
     * Whether the Captcha module is installed and enabled.
     *
     * Safe when the module is absent: it short-circuits instead of resolving a
     * class that does not exist on disk. Captcha is a form *field*: this only
     * tells whether the type is offered/honoured, never forces it on a form.
     */
    function captcha_available(): bool
    {
        return app(FieldTypeRegistry::class)->get('captcha')?->available() ?? false;
    }
}

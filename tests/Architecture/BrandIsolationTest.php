<?php

declare(strict_types=1);
use Composer\InstalledVersions;
use DbCms\Core\Cms;

/**
 * Guardrails for the brand/theme refactor.
 *
 * The brand identity belongs to the active theme manifest
 * (packages/admin-themes/{id}/theme.json). Configuration files must stay
 * brand-agnostic and never branch on a specific theme identifier.
 *
 * @see docs/EXTENSIONS.md
 */
it('never hardcodes a brand identifier in configuration files', function (): void {
    $forbidden = ['fixture-admin', 'dbcms', 'Fixture Admin', 'Fixture Admin'];

    foreach (glob(Cms::path('config/*.php')) ?: [] as $file) {
        $contents = (string) file_get_contents($file);
        $relative = 'config/'.basename($file);
        $contents = preg_replace('~dbravoan/dbcms(?:-[a-z-]+)?~', 'upstream/package', $contents) ?? $contents;
        foreach ($forbidden as $needle) {
            // Allowed only as an `env('...', 'default')` fallback value.
            $allowedEnvPattern = "/env\(\s*'[^']*'\s*,\s*'".preg_quote($needle, '/')."'\s*\)/";

            $stripped = preg_replace($allowedEnvPattern, '', $contents) ?? $contents;

            expect(str_contains($stripped, $needle))
                ->toBeFalse("Configuration file {$relative} hardcodes the brand '{$needle}'. Brand data belongs to the theme manifest, not to config.");
        }
    }
});

it('does not branch on a specific theme identifier inside config', function (): void {
    $contents = (string) file_get_contents(Cms::path('config/cms.php'));

    expect($contents)->not->toContain("=== 'fixture-admin'")
        ->not->toContain('=== "fixture-admin"')
        ->not->toContain("=== 'dbcms'")
        ->not->toContain('=== "dbcms"');
});

it('declares the brand identity only inside backoffice theme manifests', function (): void {
    $manifests = [Cms::path('resources/admin-theme/theme.json'), InstalledVersions::getInstallPath('fixture/admin-theme').'/theme.json'];

    expect($manifests)->not->toBeEmpty();

    foreach ($manifests as $manifest) {
        $data = json_decode((string) file_get_contents($manifest), true);

        expect($data)->toBeArray();
        expect(array_key_exists('brand', $data))->toBeTrue();

        foreach (['name', 'tagline', 'accent', 'mode'] as $key) {
            expect(array_key_exists($key, $data['brand']))
                ->toBeTrue("Theme manifest {$manifest} brand block is missing '{$key}'.");
        }
    }
});

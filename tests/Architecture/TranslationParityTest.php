<?php

declare(strict_types=1);
use DbCms\Core\Cms;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Services\Cms\BackofficeLocaleService;

/**
 * Guarantees that every backoffice translation file keeps perfect parity
 * between the supported locales (es/en). This prevents shipping a locale
 * with missing or extra keys.
 */

/**
 * @param  array<string, mixed>  $array
 * @return list<string>
 */
function flattenTranslationKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = array_merge($keys, flattenTranslationKeys($value, $full));
        } else {
            $keys[] = $full;
        }
    }

    sort($keys);

    return $keys;
}

/**
 * Project root, resolved without booting the application.
 */
function projectRoot(): string
{
    return PACKAGE_ROOT;
}

test('backoffice php translation files have identical keys across locales', function (): void {
    $locales = ['es', 'en'];
    $langPath = Cms::path('lang');

    $files = collect(glob($langPath.'/'.$locales[0].'/*.php'))
        ->map(static fn (string $path): string => basename($path))
        ->values();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $reference = [];
        foreach ($locales as $locale) {
            $path = $langPath.'/'.$locale.'/'.$file;
            expect(file_exists($path))->toBeTrue("Missing translation file [{$locale}/{$file}].");

            /** @var array<string, mixed> $content */
            $content = require $path;
            $reference[$locale] = flattenTranslationKeys($content);
        }

        expect($reference['en'])->toBe(
            $reference['es'],
            "Key parity mismatch in [{$file}] between es and en."
        );
    }
});

test('json translation files have identical keys across locales', function (): void {
    $locales = ['es', 'en'];
    $langPath = Cms::path('lang');

    $reference = [];
    foreach ($locales as $locale) {
        $path = $langPath.'/'.$locale.'.json';
        expect(file_exists($path))->toBeTrue("Missing JSON translation file [{$locale}.json].");

        /** @var array<string, mixed> $content */
        $content = json_decode((string) file_get_contents($path), true);
        $keys = array_keys($content);
        sort($keys);
        $reference[$locale] = $keys;
    }

    expect($reference['en'])->toBe($reference['es'], 'JSON key parity mismatch between es and en.');
});

test('backoffice navigation uses translation keys instead of hardcoded labels', function (): void {
    $content = (string) file_get_contents(Cms::path('src/Services/Admin/AdminNavigationService.php'));

    expect($content)->toContain("__('admin.nav.items.")
        ->and($content)->toContain("__('admin.nav.sections.");

    // The most visible hardcoded Spanish labels must be gone.
    expect($content)->not->toContain("label: 'Panel principal'")
        ->and($content)->not->toContain("label: 'Páginas'")
        ->and($content)->not->toContain("label: 'Artículos del blog'");
});

test('the native backoffice layout exposes its language switcher', function (): void {
    $path = Cms::path('resources/views/admin/layout.blade.php');
    expect(is_file($path))->toBeTrue();
    expect(file_get_contents($path))->toContain('x-admin.locale-switcher');
});

test('backoffice locale service and middleware exist', function (): void {
    expect(class_exists(BackofficeLocaleService::class))->toBeTrue()
        ->and(class_exists(SetBackofficeLocale::class))->toBeTrue();
});

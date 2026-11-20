<?php

declare(strict_types=1);

/**
 * Guards the Admin UI Framework contract: single source of truth for
 * components and no re-implementation of market/language logic in views.
 *
 * Inspects the actual owning package views.
 */
$basePath = PACKAGE_ROOT;

test('the duplicated admin components directory does not exist', function () use ($basePath): void {
    expect(is_dir($basePath.'/resources/views/admin/components'))
        ->toBeFalse('resources/views/admin/components must not exist: use <x-admin.*> only.');
});

test('market and language behaviour is only implemented inside the kit components', function () use ($basePath): void {
    $needles = ['isLanguageActive', 'toggleMarket', 'marketLanguagesMap', 'dispatchMarketsChanged'];

    $allowed = [
        $basePath.'/resources/views/components/admin/market-picker.blade.php',
        $basePath.'/resources/views/components/admin/locale-tabs.blade.php',
        $basePath.'/resources/views/components/admin/block-manager.blade.php',
    ];

    $violations = [];

    $directories = [
        $basePath.'/resources/views/admin',
        $basePath.'/resources/views/components',
    ];

    foreach ($directories as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (in_array($path, $allowed, true)) {
                continue;
            }

            $content = (string) file_get_contents($path);

            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    $violations[] = str_replace($basePath.'/', '', $path).' => '.$needle;
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

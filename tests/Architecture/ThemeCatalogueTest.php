<?php

declare(strict_types=1);

use DbCms\Core\Services\Cms\ThemeManagerService;

beforeEach(function (): void {
    $this->frontThemes = app(ThemeManagerService::class)->getAvailableThemes();
    expect($this->frontThemes)->not->toBeEmpty();
});

test('installed external themes are discovered with their own layout', function (): void {
    $themes = app(ThemeManagerService::class)->getAvailableThemes();

    $required = [
        'theme.json',
        'theme.css',
        'layouts/app.blade.php',
        'partials/header.blade.php',
        'partials/footer.blade.php',
    ];

    foreach ($this->frontThemes as $id => $package) {
        expect(array_key_exists($id, $themes))->toBeTrue("Theme {$id} is not discovered.");

        foreach ($required as $file) {
            expect(file_exists($package['directory'].'/'.$file))
                ->toBeTrue("Theme {$id} is missing {$file}.");
        }
    }
});

test('all front themes are self-contained without cross-theme references', function (): void {
    foreach ($this->frontThemes as $id => $package) {
        $base = $package['directory'];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('/themes::(db-[a-z0-9\-]+)\./', $content, $matches);

            foreach (array_unique($matches[1]) as $referenced) {
                expect($referenced)->toBe($id, "Theme {$id} references another theme [{$referenced}] in {$file->getFilename()}.");
            }
        }
    }
});

test('every front layout exposes the required hooks', function (): void {
    foreach ($this->frontThemes as $id => $package) {
        $layout = (string) file_get_contents($package['directory'].'/layouts/app.blade.php');

        expect($layout)->toContain("@yield('content')")
            ->and($layout)->toContain("@stack('scripts')")
            ->and($layout)->toContain("@stack('admin_scripts')")
            ->and($layout)->toContain('components.frontoffice-admin-bar')
            ->and($layout)->toContain('themes::components.cookie-consent-modal')
            ->and($layout)->toContain('themes::components.visitor-geo-modal')
            ->and($layout)->toContain('themes::components.scroll-magic');
    }
});

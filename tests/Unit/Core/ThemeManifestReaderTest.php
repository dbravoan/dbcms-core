<?php

declare(strict_types=1);

use DbCms\Core\Themes\ThemeManifestReader;

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/dbcms-themes-'.uniqid('', true);
    mkdir($this->themesDir.'/acme', 0700, true);
});

afterEach(function (): void {
    foreach (glob($this->themesDir.'/acme/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->themesDir.'/acme');
    rmdir($this->themesDir);
});

test('it decodes a valid theme manifest', function (): void {
    file_put_contents(
        $this->themesDir.'/acme/theme.json',
        json_encode(['name' => 'Acme', 'version' => '2.1.0']),
    );

    $manifest = (new ThemeManifestReader)->read($this->themesDir.'/acme');

    expect($manifest)->toBe(['name' => 'Acme', 'version' => '2.1.0']);
});

test('it returns an empty array when the manifest is missing', function (): void {
    expect((new ThemeManifestReader)->read($this->themesDir.'/acme'))->toBe([]);
});

test('it returns an empty array for malformed json', function (): void {
    file_put_contents($this->themesDir.'/acme/theme.json', '{not valid json');

    expect((new ThemeManifestReader)->read($this->themesDir.'/acme'))->toBe([]);
});

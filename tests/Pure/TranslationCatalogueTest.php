<?php

declare(strict_types=1);
use DbCms\Core\Cms;

test('json translation files keep key parity across all three locales', function (): void {
    $langPath = Cms::path('lang');

    $en = array_keys((array) json_decode((string) file_get_contents($langPath.'/en.json'), true));
    $es = array_keys((array) json_decode((string) file_get_contents($langPath.'/es.json'), true));
    $ca = array_keys((array) json_decode((string) file_get_contents($langPath.'/ca.json'), true));

    sort($en);
    sort($es);
    sort($ca);

    expect($es)->toBe($en)
        ->and($ca)->toBe($en);
});

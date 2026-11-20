<?php

declare(strict_types=1);
use DbCms\Core\Cms;

/**
 * The friendly-URL standard (docs/EXTENSIONS.md): front-office modules must resolve
 * filters/searches through the core FilterSet, never by reading filter values
 * straight from the query string (which would create duplicate, non-canonical
 * URLs).
 */
test('front controllers resolve filters from friendly URLs, not query params', function (): void {
    $files = [Cms::path('src/Content/Pages/Http/Controllers/FrontPageController.php'), Cms::path('src/Search/Http/Controllers/SearchFrontController.php')];

    expect($files)->not->toBeEmpty();

    $pattern = "/request\(\s*['\"](?:q|page|category|sort|filter|search)['\"]\s*\)"
        ."|request\(\)->(?:query|input|get)\(\s*['\"](?:q|page|category|sort|filter|search)['\"]/i";

    $offenders = [];

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);

        if (preg_match_all($pattern, $contents, $matches) > 0) {
            $offenders[] = basename($file).' → '.implode(', ', array_unique($matches[0]));
        }
    }

    expect($offenders)->toBeEmpty(
        'Front controllers must use DbCms\\Core\\Filters\\FilterSet instead of filter query params: '.implode(' | ', $offenders)
    );
});

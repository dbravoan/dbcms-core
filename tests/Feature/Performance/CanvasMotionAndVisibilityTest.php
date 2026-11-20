<?php

declare(strict_types=1);
use DbCms\Core\Cms;

/**
 * Guardrail for animated canvases: they must honour `prefers-reduced-motion`
 * and release the animation frame when the tab is hidden, instead of spinning
 * requestAnimationFrame forever.
 */
test('animated canvases respect reduced motion and pause when the tab is hidden', function (string $view): void {
    $contents = (string) file_get_contents(Cms::path($view));

    expect($contents)
        ->toContain('prefers-reduced-motion')
        ->toContain('visibilitychange')
        ->toContain('cancelAnimationFrame');
})->with([
    'backoffice maintenance canvas' => 'resources/views/maintenance/index.blade.php',
]);

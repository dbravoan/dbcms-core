<?php

declare(strict_types=1);
use DbCms\Core\Cms;

it('keeps the native backoffice and installation views free of blocking third party CDNs', function (): void {
    $views = [
        Cms::path('resources/views/admin/layout.blade.php'),
        Cms::path('resources/views/admin/auth/login.blade.php'),
        Cms::path('resources/views/errors/layout.blade.php'),
    ];
    foreach ($views as $view) {
        expect(is_file($view))->toBeTrue();
        $contents = (string) file_get_contents($view);
        foreach (['cdn.tailwindcss.com', 'cdnjs.cloudflare.com', 'cdn.jsdelivr.net', 'unpkg.com'] as $host) {
            expect($contents)->not->toContain($host);
        }
        expect($contents)->toContain('@vite(');
    }
});

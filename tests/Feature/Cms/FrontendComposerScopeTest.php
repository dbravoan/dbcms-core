<?php

declare(strict_types=1);

use DbCms\Core\Services\Cms\MenuService;
use Illuminate\Support\Facades\File;
use Tests\Support\CmsFixtures;

test('frontend context follows public namespaces including project overrides', function (string $view, bool $public): void {
    CmsFixtures::localization();
    $directory = sys_get_temp_dir().'/dbcms-view-scope-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($directory.'/admin/partials');
    File::ensureDirectoryExists($directory.'/partials');
    $template = "{{ \$headerMenu[0]['title'] ?? 'No public context' }}";
    File::put($directory.'/admin/partials/scope.blade.php', $template);
    File::put($directory.'/partials/scope.blade.php', $template);
    view()->addNamespace('ownership', $directory);
    view()->addLocation($directory);
    // Project overrides retain the public namespace, just as registered themes do.
    view()->prependNamespace('theme-fixture-theme', $directory);
    view()->prependNamespace('themes', $directory);
    $resolved = 0;
    app()->bind(MenuService::class, function () use (&$resolved): MenuService {
        $resolved++;
        $menus = Mockery::mock(MenuService::class);
        $menus->shouldReceive('getMenu')->andReturn([['title' => 'Public context', 'url' => '/example']]);

        return $menus;
    });
    try {
        expect(trim(view($view)->render()))->toBe($public ? 'Public context' : 'No public context')
            ->and($resolved)->toBe($public ? 1 : 0);
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    ['ownership::admin.partials.scope', false],
    ['admin.partials.scope', false],
    ['theme-fixture-theme::partials.scope', true],
    ['themes::partials.scope', true],
]);

<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

test('switching admin themes restores module views and preserves project precedence', function (): void {
    $namespace = 'theme-switch-fixture';
    $base = storage_path('framework/testing/'.$namespace);
    $project = resource_path('views/vendor/'.$namespace);
    $projectTheme = resource_path('views/themes/admin/fixture-admin/modules/'.$namespace);
    foreach ([$base, $base.'/secondary', $base.'/themes/fixture-admin', $project, $projectTheme] as $directory) {
        File::ensureDirectoryExists($directory);
    }
    file_put_contents($base.'/sample.blade.php', 'Owner base');
    file_put_contents($base.'/themes/fixture-admin/sample.blade.php', 'Owner theme variant');
    file_put_contents($base.'/secondary/extra.blade.php', 'Secondary owner path');
    View::addNamespace($namespace, [$base, $base.'/secondary']);
    $themes = app(AdminThemeServiceInterface::class);
    try {
        $themes->setActiveTheme('fixture-admin');
        $themes->registerModuleThemePaths($namespace, $base);
        expect(view($namespace.'::sample')->render())->toBe('Owner theme variant');

        $themes->setActiveTheme('dbcms');
        expect(view($namespace.'::sample')->render())->toBe('Owner base');

        file_put_contents($project.'/sample.blade.php', 'Project override');
        $themes->setActiveTheme('fixture-admin');
        expect(view($namespace.'::sample')->render())->toBe('Project override');

        file_put_contents($projectTheme.'/sample.blade.php', 'Project theme override');
        $themes->setActiveTheme('dbcms');
        expect(view($namespace.'::sample')->render())->toBe('Project override');
        $themes->setActiveTheme('fixture-admin');
        expect(view($namespace.'::sample')->render())->toBe('Project theme override');

        $hints = View::getFinder()->getHints()[$namespace];
        $themes->registerModuleThemePaths($namespace, $base);
        expect(View::getFinder()->getHints()[$namespace])->toBe($hints)
            ->and($hints)->toHaveCount(count(array_unique($hints)))
            ->and(view($namespace.'::extra')->render())->toBe('Secondary owner path');
    } finally {
        $themes->setActiveTheme('dbcms');
        foreach ([$base, $project, $projectTheme] as $directory) {
            File::deleteDirectory($directory);
        }
    }
});

<?php

declare(strict_types=1);

namespace Tests\Support;

use DbCms\Core\Extensions\ComposerPackages;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/** Installed third-party packages in a private directory, with no project/vendor writes. */
final class ThemePackageFixture
{
    private ComposerPackageInventory $inventory;

    public function __construct()
    {
        $this->inventory = new ComposerPackageInventory;
    }

    public function front(string $id): string
    {
        $directory = $this->inventory->add('fixture/'.$id, 'dbcms-theme', ['identifier' => $id]);
        $source = app(ComposerPackages::class)->all('dbcms-theme')['fixture-theme']['directory'];
        foreach (['layouts', 'partials'] as $part) {
            File::copyDirectory($source.'/'.$part, $directory.'/'.$part);
        }
        foreach (File::allFiles($directory) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                File::put($file->getPathname(), str_replace('fixture-theme', $id, File::get($file->getPathname())));
            }
        }
        File::put($directory.'/theme.json', json_encode(['identifier' => $id, 'name' => 'Independent '.$id], JSON_THROW_ON_ERROR));
        File::put($directory.'/theme.css', ':root { --fixture-theme: 1; }');
        app()->forgetInstance(ComposerPackages::class);
        View::addNamespace('theme-'.$id, $directory);

        return $directory;
    }

    public function admin(string $id): string
    {
        $source = app(ComposerPackages::class)->all('dbcms-admin-theme')['fixture-admin']['directory'];
        $directory = $this->inventory->directory.'/'.$id;
        File::copyDirectory($source, $directory);
        foreach (File::allFiles($directory) as $file) {
            if (in_array($file->getExtension(), ['php', 'css'], true)) {
                File::put($file->getPathname(), str_replace('theme-dbcms', 'theme-'.$id, File::get($file->getPathname())));
            }
        }
        $this->inventory->add('fixture/'.$id, 'dbcms-admin-theme', ['identifier' => $id], directory: $directory);
        File::put($directory.'/theme.json', json_encode(['identifier' => $id, 'name' => 'Independent '.$id], JSON_THROW_ON_ERROR));
        app()->forgetInstance(ComposerPackages::class);

        return $directory;
    }

    public function restore(): void
    {
        $this->inventory->restore();
        app()->forgetInstance(ComposerPackages::class);
    }
}

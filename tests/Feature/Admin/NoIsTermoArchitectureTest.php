<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DbCms\Core\Cms;
use Tests\TestCase;

class NoIsTermoArchitectureTest extends TestCase
{
    /**
     * Verify that no PHP, Blade, or JS files contain references to isTermo or isDbCms.
     * Backoffice theming must be 100% CSS class/attribute driven (e.g. html.theme-fixture-admin in fixture-admin.css).
     */
    public function test_no_is_termo_or_is_dbcms_flags_in_application_code(): void
    {
        $scanDirectories = [
            app_path(),
            resource_path('views'),
            Cms::path('src'),
            Cms::path('resources/views'),
        ];

        foreach ($scanDirectories as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $extension = $file->getExtension();

                if (! in_array($extension, ['php', 'blade', 'js'], true)) {
                    continue;
                }

                $content = file_get_contents($path);
                $this->assertNotFalse($content, "Failed to read file: {$path}");

                $this->assertStringNotContainsString(
                    'isTermo',
                    $content,
                    "Found forbidden 'isTermo' reference in: {$path}"
                );

                $this->assertStringNotContainsString(
                    'isDbCms',
                    $content,
                    "Found forbidden 'isDbCms' reference in: {$path}"
                );
            }
        }
    }
}

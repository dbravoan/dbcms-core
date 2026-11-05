<?php

declare(strict_types=1);

namespace DbCms\Core;

use Composer\InstalledVersions;

final class Cms
{
    public static function path(string $relative = ''): string
    {
        $root = InstalledVersions::getInstallPath('dbravoan/dbcms-core')
            ?? throw new \RuntimeException('The dbCMS core Composer package is not installed.');

        return $root.($relative === '' ? '' : '/'.ltrim($relative, '/'));
    }
}

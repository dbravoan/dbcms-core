<?php

declare(strict_types=1);

namespace DbCms\Core\Themes;

use Illuminate\Support\Facades\File;

/**
 * Reads and decodes `theme.json` manifests.
 *
 * The front-office theme manager, the backoffice theme service and the brand
 * registry each used to decode the manifest on their own. This is the single
 * reader; consumers keep their own normalisation/defaults on top of it.
 */
final class ThemeManifestReader
{
    public const MANIFEST_FILE = 'theme.json';

    /**
     * Decode the manifest inside a theme directory.
     *
     * Returns an empty array when the file is missing, unreadable or not a JSON
     * object, so callers can apply their own defaults without try/catch noise.
     *
     * @return array<string, mixed>
     */
    public function read(string $themeDirectory): array
    {
        $path = rtrim($themeDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}

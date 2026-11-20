<?php

declare(strict_types=1);

namespace Tests\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;

/** A disposable Composer inventory; package code can live outside vendor. */
final class ComposerPackageInventory
{
    /** @var array<string, mixed> */
    private array $previous;

    public readonly string $directory;

    public function __construct()
    {
        $this->previous = InstalledVersions::getRawData();
        $this->directory = sys_get_temp_dir().'/dbcms-packages-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    /** @param array<string, mixed> $metadata
     * @param  array<string, string>  $requires
     */
    public function add(string $name, string $type, array $metadata, array $requires = [], string $version = '1.0.0', ?string $directory = null): string
    {
        $directory ??= $this->directory.'/'.str_replace('/', '-', $name);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/composer.json', json_encode([
            'name' => $name, 'type' => $type, 'require' => $requires,
            'extra' => ['dbcms' => $metadata],
        ], JSON_THROW_ON_ERROR));
        $data = InstalledVersions::getRawData();
        $data['versions'][$name] = [
            'type' => $type, 'version' => $version, 'pretty_version' => $version,
            'install_path' => $directory, 'aliases' => [], 'dev_requirement' => false,
        ];
        InstalledVersions::reload($data);

        return $directory;
    }

    public function restore(): void
    {
        InstalledVersions::reload($this->previous);
        File::deleteDirectory($this->directory);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Extensions;

use Composer\InstalledVersions;
use DomainException;

/** Reads Composer's installed inventory; never installs or activates CMS code. */
final class ComposerPackages
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $inventory = [];

    /** @return array<string, array<string, mixed>> */
    public function all(string $type): array
    {
        if (isset($this->inventory[$type])) {
            return $this->inventory[$type];
        }
        $packages = [];
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType($type)) as $name) {
            $directory = InstalledVersions::getInstallPath($name);
            if ($directory === null || ! is_file($directory.'/composer.json')) {
                throw new DomainException("Composer package [{$name}] has no readable installation metadata.");
            }
            $composer = json_decode((string) file_get_contents($directory.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
            $metadata = $composer['extra']['dbcms'] ?? null;
            if (! is_array($metadata) || ! is_string($metadata['identifier'] ?? null)
                || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $metadata['identifier']) !== 1) {
                throw new DomainException("Composer package [{$name}] needs a valid extra.dbcms.identifier.");
            }
            $identifier = $metadata['identifier'];
            if (isset($packages[$identifier])) {
                throw new DomainException("Duplicate {$type} identifier [{$identifier}].");
            }
            $packages[$identifier] = array_replace($metadata, [
                'package' => $name,
                'name' => $metadata['name'] ?? $composer['description'] ?? $name,
                'version' => InstalledVersions::getPrettyVersion($name) ?? 'dev-main',
                'directory' => rtrim($directory, '/'),
                'composer_requires' => $composer['require'] ?? [],
            ]);
        }
        ksort($packages);

        return $this->inventory[$type] = $packages;
    }

    /** @return array<string, array<string, mixed>> */
    public function modules(): array
    {
        $modules = $this->all('dbcms-module');
        $identifiers = ['dbravoan/dbcms-core' => 'core'];
        foreach ($modules as $identifier => $module) {
            $identifiers[$module['package']] = $identifier;
        }
        foreach ($modules as &$module) {
            $settings = $module['directory'].'/module.json';
            $contributions = is_file($settings)
                ? json_decode((string) file_get_contents($settings), true, flags: JSON_THROW_ON_ERROR)
                : [];
            if (! is_array($contributions)) {
                throw new DomainException('Invalid CMS module contributions.');
            }
            $module = array_replace($contributions, $module);
            $module['requires'] = [];
            foreach ($module['composer_requires'] as $package => $constraint) {
                if (isset($identifiers[$package])) {
                    $module['requires'][$identifiers[$package]] = $constraint;
                }
            }
        }
        unset($module);

        return $modules;
    }
}

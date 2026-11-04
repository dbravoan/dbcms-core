<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use DomainException;

/** Validates module manifests and resolves explicit selections without side effects. */
final class ModuleManifestGraph
{
    /** @var array<string, array<string, mixed>> */
    private array $manifests = [];

    /** @param array<array<string, mixed>> $manifests */
    public function __construct(array $manifests, private readonly string $coreVersion)
    {
        foreach ($manifests as $manifest) {
            self::validateManifest($manifest);
            $identifier = (string) $manifest['identifier'];
            if (isset($this->manifests[$identifier])) {
                throw new DomainException("Duplicate module identifier [{$identifier}].");
            }
            $this->manifests[$identifier] = $manifest;
        }
        ksort($this->manifests);
    }

    /** @param array<string, mixed> $manifest */
    public static function validateManifest(array $manifest): void
    {
        $identifier = $manifest['identifier'] ?? null;
        if (! is_string($identifier) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $identifier) || in_array($identifier, ['core', 'pages'], true)) {
            throw new DomainException('Invalid or reserved module identifier.');
        }
        if (! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '' || strlen($manifest['name']) > 100) {
            throw new DomainException("Module [{$identifier}] needs a valid name.");
        }
        try {
            if (! is_string($manifest['version'] ?? null)) {
                throw new \UnexpectedValueException('Missing Composer version.');
            }
            (new VersionParser)->normalize($manifest['version']);
        } catch (\UnexpectedValueException $error) {
            throw new DomainException("Module [{$identifier}] needs a valid Composer version.", previous: $error);
        }
        if (! is_string($manifest['provider'] ?? null) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/D', $manifest['provider'])) {
            throw new DomainException("Module [{$identifier}] needs a fully qualified provider class.");
        }
        if (array_key_exists('is_core', $manifest)) {
            throw new DomainException('Optional modules cannot claim core status.');
        }
        if (isset($manifest['availability_setting'])) {
            $key = $manifest['availability_setting'];
            if (! is_string($key) || ($manifest['settings'][$key]['type'] ?? null) !== 'boolean') {
                throw new DomainException("Module [{$identifier}] availability setting must reference a boolean field.");
            }
        }
        $requires = $manifest['requires'] ?? [];
        if (! is_array($requires)) {
            throw new DomainException("Module [{$identifier}] requirements must be a version map.");
        }
        $parser = new VersionParser;
        foreach ($requires as $dependency => $constraint) {
            if (! is_string($dependency) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $dependency) || ! is_string($constraint) || trim($constraint) === '') {
                throw new DomainException("Module [{$identifier}] has an invalid requirement.");
            }
            try {
                $parser->parseConstraints($constraint);
            } catch (\UnexpectedValueException $error) {
                throw new DomainException("Module [{$identifier}] has an invalid version constraint [{$constraint}].", previous: $error);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function manifests(): array
    {
        return $this->manifests;
    }

    /** @param list<string> $selected
     * @return list<string>
     */
    public function ordered(array $selected): array
    {
        $selected = array_values(array_unique($selected));
        sort($selected);
        $selection = array_fill_keys($selected, true);
        $states = [];
        $ordered = [];
        foreach ($selected as $identifier) {
            $this->visit($identifier, $selection, $states, $ordered);
        }

        return $ordered;
    }

    /** @param list<string> $active
     * @return list<string>
     */
    public function dependents(string $identifier, array $active): array
    {
        $dependents = [];
        foreach ($active as $candidate) {
            $requires = (array) ($this->manifests[$candidate]['requires'] ?? []);
            if ($candidate !== $identifier && array_key_exists($identifier, $requires)) {
                $dependents[] = $candidate;
            }
        }
        sort($dependents);

        return $dependents;
    }

    /** @param array<string, true> $selection
     * @param  array<string, int>  $states
     * @param  list<string>  $ordered
     */
    private function visit(string $identifier, array $selection, array &$states, array &$ordered): void
    {
        if (($states[$identifier] ?? 0) === 2) {
            return;
        }
        if (($states[$identifier] ?? 0) === 1) {
            throw new DomainException("Module dependency cycle at [{$identifier}].");
        }
        $manifest = $this->manifests[$identifier] ?? throw new DomainException("Selected module [{$identifier}] is unavailable.");
        $states[$identifier] = 1;
        /** @var array<string, string> $requires */
        $requires = $manifest['requires'] ?? [];
        ksort($requires);
        foreach ($requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                if (! Semver::satisfies($this->coreVersion, $constraint)) {
                    throw new DomainException("Core version [{$this->coreVersion}] does not satisfy [{$constraint}] required by [{$identifier}].");
                }

                continue;
            }
            if (! isset($this->manifests[$dependency])) {
                throw new DomainException("Module [{$identifier}] requires unavailable module [{$dependency}].");
            }
            if (! isset($selection[$dependency])) {
                throw new DomainException("Module [{$identifier}] requires selected module [{$dependency}].");
            }
            $version = (string) $this->manifests[$dependency]['version'];
            if (! Semver::satisfies($version, $constraint)) {
                throw new DomainException("Module [{$dependency}] version [{$version}] does not satisfy [{$constraint}] required by [{$identifier}].");
            }
            $this->visit($dependency, $selection, $states, $ordered);
        }
        $states[$identifier] = 2;
        $ordered[] = $identifier;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use Closure;
use DbCms\Core\Capabilities\Contracts\CapabilityInterface;

final class CapabilityRegistry
{
    /** @var array<string, CapabilityInterface>
     */
    private array $capabilities = [];

    /** @var array<string, array{module: string|null, effect: string, availability: Closure|null, dependencies: list<string>}>
     */
    private array $metadata = [];

    /** @param list<string> $dependencies */
    public function register(CapabilityInterface $capability, ?string $module = null, ?string $effect = null, ?Closure $availability = null, array $dependencies = []): void
    {
        $name = $capability->name();
        $this->capabilities[$name] = $capability;
        $this->metadata[$name] = ['module' => $module, 'effect' => $effect ?? ($capability->destructive() ? 'delete' : ($capability->mutating() ? 'draft' : 'read')), 'availability' => $availability, 'dependencies' => $dependencies];
    }

    public function get(string $name): ?CapabilityInterface
    {
        return $this->capabilities[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->capabilities[$name]);
    }

    /**
     * @return array<string, CapabilityInterface>
     */
    public function all(): array
    {
        ksort($this->capabilities);

        return $this->capabilities;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array{module: string|null, effect: string, availability: Closure|null, dependencies: list<string>}
     */
    public function metadata(string $name): array
    {
        return $this->metadata[$name] ?? ['module' => null, 'effect' => 'read', 'availability' => null, 'dependencies' => []];
    }
}

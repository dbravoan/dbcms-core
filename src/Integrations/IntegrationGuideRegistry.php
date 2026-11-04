<?php

declare(strict_types=1);

namespace DbCms\Core\Integrations;

use DbCms\Core\DTOs\Integrations\SetupGuideDescriptor;
use InvalidArgumentException;
use LogicException;

final class IntegrationGuideRegistry
{
    /** @var array<string,array<string,SetupGuideDescriptor>> */
    private array $guides = [];

    /** @param iterable<SetupGuideDescriptor> $guides */
    public function register(string $module, iterable $guides): void
    {
        if (! preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $module)) {
            throw new InvalidArgumentException('Invalid integration guide module identity.');
        }
        foreach ($guides as $guide) {
            if (isset($this->guides[$module][$guide->id])) {
                throw new LogicException('An integration guide is already registered for this module.');
            }
            $this->guides[$module][$guide->id] = $guide;
        }
    }

    /** @return array<string,array<string,SetupGuideDescriptor>> */
    public function all(?string $module = null): array
    {
        return $module === null ? $this->guides : array_intersect_key($this->guides, [$module => true]);
    }

    public function find(string $module, string $id): ?SetupGuideDescriptor
    {
        return $this->guides[$module][$id] ?? null;
    }
}

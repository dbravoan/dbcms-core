<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use Closure;
use DbCms\Core\Capabilities\Contracts\CapabilityInterface;

final readonly class CallableCapability implements CapabilityInterface
{
    /**
     * @param  array<string, array<string, mixed>>  $input
     * @param  list<string>  $requiredFields
     * @param  Closure(array<string,mixed>):array<string,mixed>  $handler
     */
    public function __construct(private string $id, private string $label, private array $input, private array $requiredFields, private ?string $requiredPermission, private string $operation, private Closure $handler) {}

    public function name(): string
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->label;
    }

    public function properties(): array
    {
        return $this->input;
    }

    public function required(): array
    {
        return $this->requiredFields;
    }

    public function permission(): ?string
    {
        return $this->requiredPermission;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->requiredPermission;
    }

    public function ability(): string
    {
        return $this->operation;
    }

    public function mutating(): bool
    {
        return $this->operation !== 'read';
    }

    public function destructive(): bool
    {
        return $this->operation === 'destructive';
    }

    public function handle(array $args): array
    {
        return ($this->handler)($args);
    }
}

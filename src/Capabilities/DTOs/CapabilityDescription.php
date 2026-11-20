<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\DTOs;

final readonly class CapabilityDescription
{
    /**
     * @param  array<string,mixed>  $parameters
     * @param  list<string>  $dependencies
     */
    public function __construct(public string $name, public string $description, public array $parameters, public string $effect, public ?string $module, public array $dependencies = []) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

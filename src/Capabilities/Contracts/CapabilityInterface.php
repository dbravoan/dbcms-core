<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

interface CapabilityInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function properties(): array;

    /**
     * @return list<string>
     */
    public function required(): array;

    public function permission(): ?string;

    /**
     * @param  array<string, mixed>  $args
     */
    public function permissionFor(array $args): ?string;

    public function ability(): string;

    public function mutating(): bool;

    public function destructive(): bool;

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array;
}

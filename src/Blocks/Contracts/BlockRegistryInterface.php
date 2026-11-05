<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

interface BlockRegistryInterface
{
    /**
     * Registers a block type definition.
     */
    public function register(BlockTypeInterface $block, ?string $package = null): void;

    /**
     * Retrieves a block type by its identifier.
     */
    public function get(string $type): ?BlockTypeInterface;

    /**
     * Retrieves all registered block types.
     *
     * @return array<string, BlockTypeInterface>
     */
    public function all(): array;

    /**
     * Retrieves all active blocks whose required module is currently installed & active.
     *
     * @return array<string, BlockTypeInterface>
     */
    public function getActiveBlocks(): array;

    /**
     * Retrieves live dynamic data payloads for select dropdowns in the block manager.
     * E.g. available forms, blog categories, blog posts, shop categories, products, gallery albums.
     *
     * @return array<string, mixed>
     */
    public function getDynamicDataPayloads(): array;

    public function isAvailable(string $type): bool;

    /** @param array<string,mixed> $payload */
    public function render(string $type, array $payload, ?Market $market = null, ?string $locale = null): string;

    /** @param array<string,mixed> $payload */
    public function validate(string $type, array $payload): void;
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Contracts;

/**
 * Discovery point for the search sources declared by the installed modules.
 *
 * Lives in core so `modules/Search` (and MCP, the admin inbox, the CLI…) can
 * consume every registered entity without depending on the modules themselves.
 */
interface SearchSourceRegistryInterface
{
    public function register(SearchSourceInterface $source): void;

    public function has(string $type): bool;

    public function get(string $type): ?SearchSourceInterface;

    /**
     * @return array<string, SearchSourceInterface>
     */
    public function all(): array;

    /**
     * @return list<string>
     */
    public function types(): array;
}

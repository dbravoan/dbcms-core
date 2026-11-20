<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

/**
 * Generic, whitelisted search across CMS entities for the reusable
 * AJAX searchable select component.
 */
interface EntitySearchServiceInterface
{
    /**
     * Registered (whitelisted) source identifiers.
     *
     * @return list<string>
     */
    public function sources(): array;

    /**
     * Search a source and return a paginated result set.
     *
     * @param  list<int|string>|null  $marketIds  Optional market scope (only applied to market-scoped sources).
     * @return array{results: list<array{value: string, label: string, icon: string, badge: string}>, has_more: bool}
     */
    public function search(
        string $source,
        ?string $query = null,
        ?string $valueKey = null,
        ?int $perPage = null,
        ?string $locale = null,
        ?array $marketIds = null,
    ): array;

    /**
     * Resolve the options for a set of already-selected values.
     *
     * @param  list<string>  $values
     * @param  list<int|string>|null  $marketIds
     * @return list<array{value: string, label: string, icon: string, badge: string}>
     */
    public function resolve(
        string $source,
        array $values,
        ?string $valueKey = null,
        ?string $locale = null,
        ?array $marketIds = null,
    ): array;
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Capabilities;

use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Services\Capabilities\SearchNavigationService;

final class SearchNavigationCapability
{
    public function register(CapabilityRegistry $registry): void
    {
        $registry->register(new CallableCapability(
            'cms_search',
            'WebMCP: general search over every indexed entity (posts, products, pages…). Returns ranked results with their canonical front-office URL, per-entity facets, suggestions when nothing matches and the index policy of the result page. Use it to answer questions or to find the exact page a user should read',
            ['query' => ['type' => 'string', 'description' => 'What to look for, in natural language (e.g. \'arquitectura modular geo\')'], 'type' => ['type' => 'string', 'description' => 'Restrict to one entity type (e.g. \'post\', \'product\'). Omit to search everything'], 'market' => ['type' => 'string', 'description' => 'Market code (default: primary market)'], 'locale' => ['type' => 'string', 'description' => 'Locale code (default: market default locale)'], 'limit' => ['type' => 'integer', 'description' => 'Max results to return (default 10, max 50)']],
            ['query'],
            'access:admin',
            'read',
            static fn (array $args): array => app(SearchNavigationService::class)->search($args),
        ));

    }
}

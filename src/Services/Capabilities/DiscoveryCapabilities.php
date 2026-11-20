<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Services\Seo\ContentQualityAuditorService;

final class DiscoveryCapabilities
{
    public function register(CapabilityRegistry $registry): void
    {
        $registry->register(new CallableCapability(
            'cms_audit_seo_geo',
            'Audits article content quality, keyword density, and LLM citability score',
            ['title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'meta_description' => ['type' => 'string'], 'primary_keyword' => ['type' => 'string']],
            ['title', 'content'],
            'manage:seo',
            'read',
            static fn (array $args): array => app(ContentQualityAuditorService::class)->audit((string) ($args['title'] ?? ''), (string) ($args['content'] ?? ''), (string) ($args['meta_description'] ?? ''), (string) ($args['primary_keyword'] ?? '')),
        ), null);

        $registry->register(new CallableCapability(
            'cms_list_filter_dimensions',
            'WebMCP: lists the addressable filter dimensions of every filterable module (blog, shop) with their localized URL markers and vocabulary, so an agent knows the exact grammar of the friendly filter URLs',
            ['module' => ['type' => 'string', 'description' => 'Filterable module key (e.g. \'blog\', \'shop\'). Omit for all'], 'market' => ['type' => 'string', 'description' => 'Market code (default: primary market)'], 'locale' => ['type' => 'string', 'description' => 'Locale code (default: market default locale)']],
            [],
            'access:admin',
            'read',
            static fn (array $args): array => app(FilterNavigationService::class)->dimensions($args['module'] ?? null, $args['market'] ?? null, $args['locale'] ?? null),
        ), null);

        $registry->register(new CallableCapability(
            'cms_resolve_filter_url',
            'WebMCP: resolves a friendly filter path or full URL into its canonical URL, hreflang cluster, result count, index policy (indexable/noindex) and SEO text. Use it before linking or citing a filtered page',
            ['module' => ['type' => 'string', 'description' => 'Filterable module key (default: \'blog\')'], 'path' => ['type' => 'string', 'description' => 'Friendly filter path or full URL (e.g. \'etiqueta/laravel/orden/recientes\')'], 'market' => ['type' => 'string', 'description' => 'Market code (default: primary market)'], 'locale' => ['type' => 'string', 'description' => 'Locale code (default: market default locale)']],
            ['path', 'module'],
            'access:admin',
            'read',
            static fn (array $args): array => app(FilterNavigationService::class)->resolve((string) $args['module'], (string) ($args['path'] ?? ''), $args['market'] ?? null, $args['locale'] ?? null),
        ), null);

        $registry->register(new CallableCapability(
            'cms_browse_content',
            'WebMCP: browses a module through the filter framework (categories, tags, price, sort, search, dates) returning the matching items and the canonical URL. Accepts a friendly \'path\' or a \'filters\' map',
            ['module' => ['type' => 'string', 'description' => 'Filterable module key: \'blog\' or \'shop\' (default: \'blog\')'], 'path' => ['type' => 'string', 'description' => 'Friendly filter path (alternative to \'filters\')'], 'filters' => ['type' => 'object', 'description' => 'Filter map, e.g. {"tag":["laravel"],"sort":"recientes"}'], 'market' => ['type' => 'string', 'description' => 'Market code (default: primary market)'], 'locale' => ['type' => 'string', 'description' => 'Locale code (default: market default locale)'], 'per_page' => ['type' => 'integer', 'description' => 'Max items to return (default 10, max 50)']],
            ['module'],
            'access:admin',
            'read',
            static fn (array $args): array => app(FilterNavigationService::class)->browse((string) $args['module'], $args),
        ), null);

    }
}

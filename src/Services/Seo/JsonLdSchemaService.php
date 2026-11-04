<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use Illuminate\Support\Facades\Config;

class JsonLdSchemaService
{
    /**
     * Builds a Schema.org WebSite + Organization graph.
     *
     * @return array<string, mixed>
     */
    public function generateSiteGraph(): array
    {
        $siteUrl = is_string($v = Config::get('app.url')) ? $v : 'http://localhost';
        $siteName = (string) (function_exists('setting') ? setting('general.site_name', Config::get('seo.defaults.title', Config::get('cms.name', 'CMS'))) : Config::get('cms.name', 'CMS'));
        $orgName = (string) (function_exists('setting') ? setting('legal.company_name', $siteName) : $siteName);
        $orgType = (string) (function_exists('setting') ? setting('seo.organization_type', 'Organization') : 'Organization');
        $logoUrl = SeoImageUrlResolver::resolve(
            function_exists('setting') ? (string) setting('general.site_logo', '') : null,
            "{$siteUrl}/images/logo.png"
        );

        $sameAs = [];
        if (function_exists('setting')) {
            $socials = ['twitter', 'linkedin', 'instagram', 'facebook', 'youtube', 'tiktok', 'github'];
            foreach ($socials as $s) {
                $url = setting("social.{$s}");
                if (! empty($url)) {
                    $sameAs[] = (string) $url;
                }
            }
        }

        $orgNode = [
            '@type' => $orgType,
            '@id' => "{$siteUrl}/#organization",
            'name' => $orgName,
            'url' => $siteUrl,
        ];
        if ($logoUrl !== null) {
            $orgNode['logo'] = ['@type' => 'ImageObject', '@id' => "{$siteUrl}/#logo", 'url' => $logoUrl];
        }

        if (! empty($sameAs)) {
            $orgNode['sameAs'] = $sameAs;
        }

        if (function_exists('setting')) {
            $taxId = setting('legal.tax_id');
            if (! empty($taxId)) {
                $orgNode['taxID'] = (string) $taxId;
            }

            $phone = setting('contact.phone');
            if (! empty($phone)) {
                $orgNode['telephone'] = (string) $phone;
            }

            $email = setting('contact.email');
            if (! empty($email)) {
                $orgNode['email'] = (string) $email;
            }

            $address = setting('legal.address');
            if (! empty($address)) {
                $orgNode['address'] = [
                    '@type' => 'PostalAddress',
                    'streetAddress' => (string) $address,
                    'postalCode' => (string) setting('legal.postal_code', ''),
                    'addressLocality' => (string) setting('legal.city', ''),
                    'addressRegion' => (string) setting('legal.province', ''),
                    'addressCountry' => (string) setting('legal.country', 'ES'),
                ];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $orgNode,
                [
                    '@type' => 'WebSite',
                    '@id' => "{$siteUrl}/#website",
                    'url' => $siteUrl,
                    'name' => $siteName,
                    'publisher' => [
                        '@id' => "{$siteUrl}/#organization",
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds a WebPage node for a concrete URL.
     *
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function generateWebPageGraph(
        string $title,
        string $description,
        string $url,
        string $locale = 'es',
        array $breadcrumbs = [],
    ): array {
        $siteUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');
        $siteName = (string) (function_exists('setting') ? setting('general.site_name', Config::get('cms.name', '')) : Config::get('cms.name', ''));

        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => $siteUrl.'/#website'],
        ];

        if ($siteName !== '') {
            $graph['publisher'] = ['@id' => $siteUrl.'/#organization'];
        }

        if ($breadcrumbs !== []) {
            $graph['breadcrumb'] = $this->generateBreadcrumb($breadcrumbs, $url);
        }

        return $graph;
    }

    /**
     * Builds an entity node (Article, Product, ImageGallery, FAQPage...) merged
     * with the WebPage graph so a single JSON-LD block carries everything.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function generateEntityGraph(
        array $entity,
        string $url,
        string $locale = 'es',
        array $breadcrumbs = [],
    ): array {
        $siteUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');

        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'inLanguage' => $locale,
                    'isPartOf' => ['@id' => $siteUrl.'/#website'],
                ],
                array_merge(['@id' => $url.'#entity', 'url' => $url, 'inLanguage' => $locale], $entity),
            ],
        ];

        if ($breadcrumbs !== []) {
            $graph['@graph'][] = $this->generateBreadcrumb($breadcrumbs, $url);
        }

        return $graph;
    }

    /**
     * JSON-LD graph of a dynamic collection/filter page (CollectionPage +
     * ItemList + BreadcrumbList), so search engines and AI engines understand
     * the page as a curated listing rather than a thin duplicate.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function generateCollectionGraph(
        string $name,
        string $description,
        string $url,
        string $locale = 'es',
        array $items = [],
        array $breadcrumbs = [],
    ): array {
        $siteUrl = rtrim((string) Config::get('app.url', 'http://localhost'), '/');
        $listId = $url.'#itemlist';

        $itemList = [];
        foreach (array_values($items) as $index => $item) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ];
        }

        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => $name,
                    'description' => $description,
                    'inLanguage' => $locale,
                    'isPartOf' => ['@id' => $siteUrl.'/#website'],
                ],
                [
                    '@type' => 'CollectionPage',
                    '@id' => $url.'#collection',
                    'url' => $url,
                    'name' => $name,
                    'description' => $description,
                    'inLanguage' => $locale,
                    'isPartOf' => ['@id' => $url.'#webpage'],
                    'mainEntity' => ['@id' => $listId],
                ],
                array_filter([
                    '@type' => 'ItemList',
                    '@id' => $listId,
                    'numberOfItems' => count($itemList),
                    'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                    'itemListElement' => $itemList,
                ], static fn (mixed $value): bool => $value !== []),
            ],
        ];

        if ($breadcrumbs !== []) {
            $graph['@graph'][] = $this->generateBreadcrumb($breadcrumbs, $url);
        }

        return $graph;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    private function generateBreadcrumb(array $breadcrumbs, string $url): array
    {
        $items = [];
        foreach (array_values($breadcrumbs) as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => $items,
        ];
    }
}

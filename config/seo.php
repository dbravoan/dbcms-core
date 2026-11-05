<?php

declare(strict_types=1);

return [
    'defaults' => [
        'title' => env('SEO_DEFAULT_TITLE', 'Multi-Market Modular CMS'),
        'description' => env('SEO_DEFAULT_DESCRIPTION', 'Multi-market, multi-language modular CMS with native SEO and Generative Engine Optimization (GEO).'),
        'canonical_url' => env('APP_URL', 'http://localhost'),
        'og_image' => env('APP_URL', 'http://localhost').'/images/og-default.png',
        'twitter_handle' => env('SEO_TWITTER_HANDLE'),
        'schema_organization' => [
            '@type' => 'Organization',
            'name' => env('SEO_ORGANIZATION_NAME'),
            'url' => env('APP_URL', 'http://localhost'),
            'logo' => env('APP_URL', 'http://localhost').'/images/logo.png',
        ],
    ],
];

<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Generative Engine Optimization (GEO) Configuration
    |--------------------------------------------------------------------------
    | Configures settings for AI indexers, RAG vector retrieval, and llms.txt.
    */
    'enabled' => (bool) env('GEO_ENABLED', true),
    'site_name' => env('GEO_SITE_NAME', 'Multi-Market Modular Platform'),
    'site_description' => env('GEO_SITE_DESCRIPTION', 'Enterprise Modular CMS with Native Multi-Market Routing, AI Copilot, and Deep GEO Capabilities'),
    'author' => env('GEO_AUTHOR'),
    'llms_txt_max_tokens' => (int) env('GEO_LLMS_TXT_MAX_TOKENS', 2048),
    'chunk_size_tokens' => 512,
    'chunk_overlap_tokens' => 64,
    'disallowed_llm_bots' => [
        // Any AI crawlers to restrict if desired
    ],
];

<?php

declare(strict_types=1);
use Composer\InstalledVersions;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Models\Setting;

/**
 * CMS runtime configuration (env-driven and brand-agnostic).
 *
 * This file must NOT contain theme/brand specific values or branches. The brand
 * identity (name, tagline, logo, icon, edition, accent, tone) lives in each
 * installed backoffice theme package (`theme.json`).
 *
 * @see docs/EXTENSIONS.md
 */
return [
    'blocks' => [
        'max_depth' => 16,
        'max_nodes' => 1000,
        'max_payload_bytes' => 1048576,
        'max_render_nodes' => 2000,
        'max_render_bytes' => 2097152,
        'max_animation_bytes' => 5242880,
        'countdown_storage' => ['enabled' => false, 'purpose' => 'preferences', 'max_days' => 30],
        'breakpoints' => ['tablet' => 1024, 'mobile' => 767],
        'classes' => [
            'stack' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '1rem'],
            'row' => ['display' => 'flex', 'flex_wrap' => 'wrap', 'gap' => '1rem'],
            'card' => ['padding' => '1.5rem', 'border_radius' => 'var(--cms-radius)', 'background_color' => 'var(--cms-surface-2)', 'color' => 'var(--cms-fg)'],
        ],
    ],

    'page_views' => [],
    'block_packages' => ['enabled' => ['basic']],
    'seed_demo' => env('CMS_SEED_DEMO', false),
    'demo_seeder' => env('CMS_DEMO_SEEDER'),
    /*
    |--------------------------------------------------------------------------
    | Theme selection
    |--------------------------------------------------------------------------
    | `CMS_BRAND` selects an installed backoffice theme package; `CMS_THEME`
    | selects an installed public (front-office) theme package.
    */
    'admin_theme' => env('CMS_BRAND', 'dbcms'),
    'theme' => env('CMS_THEME', 'db-cyber'),

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    | `APP_NAME` is the site/product name used by SEO, OpenGraph and legal
    | surfaces. The visual brand shown in the backoffice comes from the active
    | theme manifest, not from here.
    */
    'name' => env('APP_NAME', 'CMS'),
    'tagline' => env('CMS_TAGLINE'),
    'admin_path' => env('ADMIN_PATH', 'admin'),
    'version' => InstalledVersions::getPrettyVersion('dbravoan/dbcms-core') ?? '1.0.0',
    'installation_id' => env('CMS_INSTALLATION_ID') ?: substr(hash('sha256', (string) env('APP_KEY', '')), 0, 32),

    /*
    |--------------------------------------------------------------------------
    | Feature flags & integrations
    |--------------------------------------------------------------------------
    */
    'ga4_measurement_id' => env('GA4_MEASUREMENT_ID', ''),
    'adsense_client_id' => env('ADSENSE_CLIENT_ID', ''),
    'meta_pixel_id' => env('META_PIXEL_ID', ''),
    'meta_capi_token' => env('META_CAPI_TOKEN', ''),
    'maintenance_mode' => env('CMS_MAINTENANCE_MODE', false),
    'lock_roles_in_production' => env('CMS_LOCK_ROLES_IN_PRODUCTION', true),
    'modules' => array_values(array_filter(array_map('trim', explode(',', (string) env('CMS_MODULES', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Public page cache
    |--------------------------------------------------------------------------
    | Full-HTML caching for public routes. Content changes constantly in this CMS,
    | so the strategy is a SHORT ttl plus event-driven invalidation: any model
    | save/delete bumps a namespace version through PageCacheObserver.
    |
    | Set CMS_PAGE_CACHE=false to disable it entirely (e.g. while debugging).
    */
    'page_cache' => [
        'enabled' => env('CMS_PAGE_CACHE', true),
        'ttl' => (int) env('CMS_PAGE_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development helpers
    |--------------------------------------------------------------------------
    | The 1-click persona switcher is a local-only convenience. It is disabled
    | unless the app runs in `local` AND the flag below is explicitly enabled.
    */
    'quick_login' => env('CMS_QUICK_LOGIN', false),
    'adminer_url' => env('CMS_ADMINER_URL'),

    /*
    |--------------------------------------------------------------------------
    | Public write throttling
    |--------------------------------------------------------------------------
    | Max public (unauthenticated) POSTs per minute and IP: form submissions,
    | comments, shop checkout/inquiry/cart and the geo preference cookie.
    */
    'public_writes_per_minute' => (int) env('CMS_PUBLIC_WRITES_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Models that invalidate the page cache when they change
    |--------------------------------------------------------------------------
    */
    'page_cache_models' => [
        Setting::class,
        Market::class,
        Language::class,
        Menu::class,
        MenuItem::class,
        Page::class,
        PageSection::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models that invalidate the whole page cache (layout/site-wide)
    |--------------------------------------------------------------------------
    | These appear on every page and/or feed version-keyed caches (menus,
    | markets, settings), so a change bumps the global namespace version.
    | The remaining models in `page_cache_models` only invalidate the pages
    | that actually read them (dependency-based invalidation).
    */
    'page_cache_global_models' => [
        Setting::class,
        Market::class,
        Language::class,
        Menu::class,
        MenuItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backoffice (admin UI) localization
    |--------------------------------------------------------------------------
    | The backoffice language is a per-user preference, independent from the
    | public market/locale context. Guests fall back to the session, then to the
    | default below.
    */
    'admin_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('CMS_ADMIN_LOCALES', 'es,en'))))),
    'default_admin_locale' => env('CMS_DEFAULT_ADMIN_LOCALE', 'es'),

    /*
    |--------------------------------------------------------------------------
    | UI option enums (single source of truth)
    |--------------------------------------------------------------------------
    | Fixed select options shared across the backoffice. Kept here so views
    | never hardcode the same list twice and a change propagates everywhere.
    | Dynamic/available things (blocks, widgets, entities...) come from their
    | registries instead, never from here.
    */
    'options' => [
        // Block form
        'columns' => [
            ['value' => '1', 'label' => 'admin.block_form.col_1'],
            ['value' => '2', 'label' => 'admin.block_form.col_2'],
            ['value' => '3', 'label' => 'admin.block_form.col_3_recommended'],
            ['value' => '4', 'label' => 'admin.block_form.col_4'],
        ],
        'ratings' => [5, 4, 3],
        'code_languages' => [
            'html' => 'admin.block_form.html_widget',
            'javascript' => 'admin.block_form.js',
            'php' => 'admin.block_form.php',
            'css' => 'admin.block_form.css',
            'json' => 'admin.block_form.json',
        ],

        // Datatable
        'per_page' => [10, 15, 25, 50, 100],

        // SEO
        'schema_types' => [
            'WebPage' => 'admin.seo.schema_webpage',
            'TechArticle' => 'admin.seo.schema_techarticle',
            'BlogPosting' => 'admin.seo.schema_blogposting',
            'Product' => 'admin.seo.schema_product',
            'FAQPage' => 'admin.seo.schema_faqpage',
            'ImageGallery' => 'admin.seo.schema_imagegallery',
        ],
        'organization_schema_types' => [
            'Organization' => 'admin.settings.schema_organization',
            'LocalBusiness' => 'admin.settings.schema_localbusiness',
            'Corporation' => 'admin.settings.schema_corporation',
            'Store' => 'admin.settings.schema_store',
            'ProfessionalService' => 'admin.settings.schema_professional',
        ],

        // Menus
        'menu_zones' => [
            'header' => 'admin.menus.zone_header',
            'footer_quick' => 'admin.menus.zone_footer_quick',
            'footer_legal' => 'admin.menus.zone_footer_legal',
            'sidebar' => 'admin.menus.zone_sidebar',
            'topbar' => 'admin.menus.zone_topbar',
            'mobile' => 'admin.menus.zone_mobile',
            'none' => 'admin.menus.zone_none',
        ],
    ],
];

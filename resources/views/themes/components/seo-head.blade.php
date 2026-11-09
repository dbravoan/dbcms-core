@php
    /**
     * Unified SEO/GEO head partial, rendered by every front-office theme.
     *
     * Emits: favicon, canonical, robots, OpenGraph, Twitter card, hreflang
     * alternates and the Schema.org JSON-LD graph. The metadata comes from the
     * layout-provided `$seoMeta` (DbCms\Core\DTOs\Seo\SeoMeta) when present, with
     * fallbacks so a theme is never left without a complete head.
     *
     * @see docs/EXTENSIONS.md
     */
    $seoMeta = $seoMeta ?? null;

    $siteFavicon = function_exists('setting') ? (string) setting('general.site_favicon', '') : '';
    $siteOgImage = function_exists('setting') ? (string) setting('general.og_image', '') : '';
    $siteName = function_exists('setting') ? (string) setting('general.site_name', '') : '';
    if ($siteName === '') {
        $siteName = (string) config('cms.name', '');
    }

    // Fallbacks when a theme only uses @section('title') / @section('meta_description').
    $metaTitle = $seoMeta?->title
        ?? (isset($pageTitle) && trim((string) $pageTitle) !== '' ? trim((string) $pageTitle) : (string) config('cms.name', ''));
    $metaDescription = $seoMeta?->description
        ?? (isset($pageDescription) ? trim((string) $pageDescription) : '');
    $canonical = $seoMeta?->canonical ?? url()->current();
    $ogType = $seoMeta?->type ?? 'website';
    $ogLocale = str_replace('-', '_', $seoMeta?->locale ?? app()->getLocale());
    $ogImage = \DbCms\Core\Services\Seo\SeoImageUrlResolver::resolve($seoMeta?->image ?: $siteOgImage);
    $robots = $seoMeta?->robots ?? (app()->isProduction() ? 'index, follow' : 'noindex, nofollow');
@endphp

@if($siteFavicon !== '')
    <link rel="icon" href="{{ $siteFavicon }}">
    <link rel="apple-touch-icon" href="{{ $siteFavicon }}">
@endif

@if($metaDescription !== '')
    <meta name="description" content="{{ $metaDescription }}">
@endif

<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

{{-- OpenGraph --}}
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $metaTitle }}">
@if($metaDescription !== '')
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@if($siteName !== '')
    <meta property="og:site_name" content="{{ $siteName }}">
@endif
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @if(($seoMeta?->imageAlt ?? $metaTitle) !== '')
        <meta property="og:image:alt" content="{{ $seoMeta?->imageAlt ?? $metaTitle }}">
    @endif
@endif

{{-- Twitter / X --}}
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $metaTitle }}">
@if($metaDescription !== '')
    <meta name="twitter:description" content="{{ $metaDescription }}">
@endif
@if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- Locale alternates: from the DTO when present, else the composer-provided hreflangs --}}
@if($seoMeta !== null && ! empty($seoMeta->alternates))
    @foreach($seoMeta->alternates as $alternate)
        <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['href'] }}">
    @endforeach
@elseif(isset($hreflangs) && is_array($hreflangs))
    @foreach($hreflangs as $hreflangTag => $href)
        <link rel="alternate" hreflang="{{ $hreflangTag }}" href="{{ $href }}">
    @endforeach
@endif

{{-- Schema.org JSON-LD graph --}}
@if($seoMeta !== null && ! empty($seoMeta->schema))
    <script type="application/ld+json">{!! json_encode($seoMeta->schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@elseif(isset($jsonLd) && is_array($jsonLd))
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif

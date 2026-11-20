<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Contracts\Services\GeoExportServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Contracts\Services\SeoAuditReportServiceInterface;
use DbCms\Core\Http\Middleware\EnsureSeoMeta;
use DbCms\Core\Services\Content\PublicContentDiscovery;
use DbCms\Core\Services\Geo\GeoExportService;
use DbCms\Core\Services\Seo\SeoAuditReportService;
use DbCms\Core\Services\Seo\SeoMetaService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the SEO/GEO view composer exactly once.
 *
 * The composer only acts on requests flagged as public HTML pages (see
 * EnsureSeoMeta) and never overwrites metadata a controller already resolved.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublicContentDiscoveryInterface::class, PublicContentDiscovery::class);
        $this->app->bind(GeoExportServiceInterface::class, GeoExportService::class);
        $this->app->bind(SeoAuditReportServiceInterface::class, SeoAuditReportService::class);
    }

    public function boot(): void
    {
        View::composer('*', function (\Illuminate\View\View $view): void {
            // Only enrich public HTML pages.
            if (request()->attributes->get(EnsureSeoMeta::PUBLIC_REQUEST_ATTRIBUTE) !== true) {
                return;
            }

            $data = $view->getData();

            // A controller-provided DTO always wins.
            if (array_key_exists('seoMeta', $data) && $data['seoMeta'] !== null) {
                return;
            }

            // Themes expose these; unrelated partials/components do not, so they
            // are skipped instead of receiving a generic metadata object.
            if (! array_key_exists('pageTitle', $data) && ! array_key_exists('pageDescription', $data)) {
                return;
            }

            $title = trim((string) ($data['pageTitle'] ?? ''));
            $description = trim((string) ($data['pageDescription'] ?? ''));

            /** @var SeoMetaService $seo */
            $seo = app(SeoMetaService::class);

            $view->with('seoMeta', $seo->forWebPage(
                title: $title !== '' ? $title : (string) config('cms.name', ''),
                description: $description !== ''
                    ? $description
                    : (string) (config('cms.tagline') ?: setting('general.site_description', '') ?: ''),
                canonical: url()->current(),
            ));
        });
    }
}

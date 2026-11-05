<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Contracts\Services\SeoServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use Illuminate\Http\Response;

final class RegionalSitemapGetController extends Controller
{
    public function __invoke(SeoServiceInterface $service, PublicDocumentCache $cache): Response
    {
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->bound('current.locale') ? (string) app('current.locale') : app()->getLocale();
        $document = $cache->remember("sitemap:{$market?->code}:{$locale}", fn (): string => $service->generateSitemapXml($market, $locale));

        return response($document, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use DbCms\Core\Services\Seo\SitemapIndexService;
use Illuminate\Http\Response;

final class SitemapIndexGetController extends Controller
{
    public function __invoke(SitemapIndexService $service, PublicDocumentCache $cache): Response
    {
        $xml = $cache->remember('sitemap:index', fn (): string => $service->indexXml());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}

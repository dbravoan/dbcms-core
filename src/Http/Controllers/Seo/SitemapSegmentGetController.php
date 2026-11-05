<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use DbCms\Core\Services\Seo\SitemapIndexService;
use Illuminate\Http\Response;

final class SitemapSegmentGetController extends Controller
{
    public function __invoke(string $segment, SitemapIndexService $service, PublicDocumentCache $cache): Response
    {
        $xml = $cache->remember('sitemap:segment:'.$segment, fn (): string => $service->segmentXml($segment) ?? '');
        abort_if($xml === '', 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}

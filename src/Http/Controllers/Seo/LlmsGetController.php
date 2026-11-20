<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Contracts\Services\GeoServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use Illuminate\Http\Response;

final class LlmsGetController extends Controller
{
    public function __invoke(GeoServiceInterface $service, PublicDocumentCache $cache): Response
    {
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->bound('current.locale') ? (string) app('current.locale') : app()->getLocale();
        $document = $cache->remember("llms:{$market?->code}:{$locale}", fn (): string => $service->generateLlmsTxt($market, $locale));

        return response($document, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

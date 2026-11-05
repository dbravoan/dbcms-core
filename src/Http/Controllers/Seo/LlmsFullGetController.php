<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Contracts\Services\GeoServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use Illuminate\Http\Response;

final class LlmsFullGetController extends Controller
{
    public function __invoke(GeoServiceInterface $service, PublicDocumentCache $cache): Response
    {
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->bound('current.locale') ? (string) app('current.locale') : app()->getLocale();
        $document = $cache->remember("llms-full:{$market?->code}:{$locale}", fn (): string => $service->generateLlmsFullTxt($market, $locale));

        return response($document, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

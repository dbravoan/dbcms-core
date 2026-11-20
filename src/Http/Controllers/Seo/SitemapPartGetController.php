<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Contracts\Services\SeoServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SitemapPartGetController extends Controller
{
    public function __invoke(Request $request, SeoServiceInterface $service): Response
    {
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->bound('current.locale') ? (string) app('current.locale') : app()->getLocale();
        $xml = $service->generateSitemapXml($market, $locale, (int) $request->route('part'));
        abort_if($xml === '', 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}

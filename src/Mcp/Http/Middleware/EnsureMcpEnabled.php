<?php

declare(strict_types=1);

namespace DbCms\Core\Mcp\Http\Middleware;

use Closure;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpEnabled
{
    public function __construct(private readonly CoreFeatureSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $this->settings->get('mcp', 'is_enabled', true), 404);

        return $next($request);
    }
}

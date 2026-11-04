<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->is('up') && is_file(base_path('release.json'))) {
            $release = json_decode((string) file_get_contents(base_path('release.json')), true);
            $sha = is_array($release) ? ($release['source_sha'] ?? null) : null;
            if (is_string($sha) && preg_match('/^[a-f0-9]{40}$/D', $sha) === 1) {
                $response->headers->set('X-dbCMS-Release', $sha);
            }
        }

        return $response;
    }
}

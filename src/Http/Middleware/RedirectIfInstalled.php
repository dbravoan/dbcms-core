<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Services\Install\InstallationAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function __construct(private readonly InstallationAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = file_exists(storage_path('installed'));
        if (! $isInstalled && $request->routeIs('install.step5')) {
            return redirect()->route('install.step1');
        }

        if ($isInstalled) {
            // Allow the completion screen right after a successful install.
            if ($request->routeIs('install.step5') && $request->session()->get('install.just_installed') === true) {
                return $next($request);
            }

            $adminPath = config('cms.admin_path', 'admin');

            return redirect("/{$adminPath}/login");
        }

        if (! $this->access->configured() && ! $request->isMethodSafe()) {
            abort(403);
        }
        if ($request->routeIs('install.access', 'install.access.store')) {
            return $next($request);
        }
        if (! $this->access->granted($request)) {
            abort_unless($request->isMethodSafe(), 403);

            return redirect()->route('install.access');
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function __construct(
        protected MaintenanceModeInterface $maintenanceService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If maintenance mode is not active, proceed normally
        if (! $this->maintenanceService->isActive()) {
            return $next($request);
        }

        // 2. Intelligent bypass rules:
        // Administrative panel routes
        $adminPath = (string) config('cms.admin_path', 'admin');
        if ($request->is("{$adminPath}*") || $request->is('admin*')) {
            return $next($request);
        }

        // Login, logout and quick-login endpoints
        if ($request->is('login*') || $request->is('logout*') || $request->is('quick-login*')) {
            return $next($request);
        }

        // Health check endpoint
        if ($request->is('up')) {
            return $next($request);
        }

        // Installer endpoints
        if ($request->is('install*')) {
            return $next($request);
        }

        // Authenticated backoffice staff bypass
        $user = Auth::guard('web')->user();
        if ($user !== null) {
            try {
                if ($user instanceof User && $user->hasPermission('access:admin')) {
                    return $next($request);
                }
            } catch (\Throwable) {
                // Fallback in case of DB migration state
            }
        }

        // 3. Render HTTP 503 response with Retry-After header and maintenance view
        return response($this->maintenanceService->renderHtml(), 503, ['Retry-After' => (string) $this->maintenanceService->getRetryAfterSeconds(), 'Cache-Control' => 'no-cache, no-store, must-revalidate', 'Pragma' => 'no-cache', 'X-CMS-Maintenance' => '1']);
    }
}

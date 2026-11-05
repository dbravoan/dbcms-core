<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleIdentifier): Response
    {
        if (! $this->moduleManager->isModuleEnabled($moduleIdentifier)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => "El módulo '{$moduleIdentifier}' se encuentra actualmente desactivado.",
                ], Response::HTTP_NOT_FOUND);
            }

            abort(Response::HTTP_NOT_FOUND, "El módulo '{$moduleIdentifier}' está desactivado.");
        }

        return $next($request);
    }
}

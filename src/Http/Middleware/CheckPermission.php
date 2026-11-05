<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! Auth::guard('web')->check()) {
            return redirect()->route('admin.login');
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if ($user->isAdmin() || $user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, "Acceso restringido: Se requiere el permiso [{$permission}] para acceder a este recurso.");
    }
}

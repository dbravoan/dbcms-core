<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleApiAuthentication
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $action = 'login'): Response
    {
        $ipKey = 'api-auth:'.$action.':ip:'.$request->ip();
        $emailKey = 'api-auth:login:account:'.hash('sha256', Str::lower(trim((string) $request->input('email', ''))));
        $limits = [$ipKey => $action === 'register' ? 5 : 30];
        if ($action === 'login') {
            $limits[$emailKey] = 5;
        }
        foreach ($limits as $key => $maximum) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $retry = RateLimiter::availableIn($key);

                return response()->json(['message' => 'Too many authentication attempts.'], 429, ['Retry-After' => (string) $retry]);
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 60);
        }
        $response = $next($request);
        if ($action === 'login' && $response->isSuccessful()) {
            RateLimiter::clear($emailKey);
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Auth;

use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginPostController extends Controller
{
    /**
     * Max failed attempts per (email + IP) before a temporary lockout.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout window, in seconds.
     */
    private const DECAY_SECONDS = 60;

    public function __invoke(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $credentials['is_service_account'] = false;

        $this->ensureIsNotRateLimited($request, $credentials['email']);

        $remember = (bool) $request->boolean('remember', false);

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::clear($this->throttleKey($request, $credentials['email']));

            $request->session()->regenerate();
            $request->session()->save();

            return redirect()->intended(route('admin.dashboard'));
        }

        RateLimiter::hit($this->throttleKey($request, $credentials['email']), self::DECAY_SECONDS);

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Blocks brute-force attempts, keyed by email + IP so neither dimension can be
     * rotated to bypass the limit on its own.
     */
    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos de acceso. Vuelve a intentarlo en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'admin-login|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Resolves and persists the backoffice (admin UI) language.
 *
 * The admin language is a per-user preference and is deliberately independent
 * from the public market/locale context: the same user always sees the
 * backoffice in the language they chose, regardless of the market they browse.
 */
class BackofficeLocaleService
{
    /**
     * @return list<string>
     */
    public function available(): array
    {
        $locales = (array) config('cms.admin_locales', ['es', 'en']);

        $normalized = [];
        foreach ($locales as $locale) {
            $code = strtolower(trim((string) $locale));
            if ($code !== '' && ! in_array($code, $normalized, true)) {
                $normalized[] = $code;
            }
        }

        return $normalized === [] ? ['es'] : $normalized;
    }

    public function default(): string
    {
        $default = strtolower(trim((string) config('cms.default_admin_locale', 'es')));

        return in_array($default, $this->available(), true) ? $default : $this->available()[0];
    }

    public function isAvailable(string $locale): bool
    {
        return in_array(strtolower($locale), $this->available(), true);
    }

    /**
     * Resolve the backoffice locale for the current request.
     */
    public function resolve(Request $request): string
    {
        $available = $this->available();

        $user = $request->user();
        if ($user !== null) {
            $userLocale = strtolower((string) ($user->locale ?? ''));
            if ($userLocale !== '' && in_array($userLocale, $available, true)) {
                return $userLocale;
            }
        }

        if ($request->hasSession()) {
            $sessionLocale = strtolower((string) $request->session()->get('backoffice_locale', ''));
            if ($sessionLocale !== '' && in_array($sessionLocale, $available, true)) {
                return $sessionLocale;
            }
        }

        return $this->default();
    }

    /**
     * Apply a locale to the application and remember it for the session/user.
     */
    public function apply(Request $request, string $locale): string
    {
        $locale = strtolower($locale);
        if (! $this->isAvailable($locale)) {
            $locale = $this->default();
        }

        App::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('backoffice_locale', $locale);
        }

        $user = $request->user();
        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $locale;
    }
}

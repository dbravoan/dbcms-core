<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use Illuminate\Http\Request;

final class InstallationAccess
{
    private const SESSION_KEY = 'install.operator';

    public function configured(): bool
    {
        return $this->digest() !== null;
    }

    public function granted(Request $request): bool
    {
        $grant = $request->session()->get(self::SESSION_KEY);
        $digest = $this->digest();

        return $digest !== null && is_string($grant) && hash_equals($digest, $grant);
    }

    public function grant(Request $request): bool
    {
        $token = $request->input('install_token');
        $digest = $this->digest();
        if ($digest === null || ! is_string($token) || strlen($token) !== 64 || ! hash_equals($digest, hash('sha256', $token))) {
            return false;
        }

        // Exclusive creation serializes redemption across requests and cache stores.
        $claim = @fopen(storage_path('install-access-'.$digest.'.claim'), 'x');
        if ($claim === false) {
            return false;
        }
        fclose($claim);

        $request->session()->regenerate(true);
        $request->session()->put(self::SESSION_KEY, $digest);

        return true;
    }

    private function digest(): ?string
    {
        $token = config('cms.install_token');

        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? hash('sha256', $token) : null;
    }
}

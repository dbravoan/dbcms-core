<?php

declare(strict_types=1);

namespace DbCms\Core\Captcha\Services;

use DbCms\Core\Security\Contracts\ContentSecurityPolicyContributorInterface;
use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class CaptchaService implements ContentSecurityPolicyContributorInterface
{
    public const DRIVER_INTERNAL = 'internal';

    public const DRIVER_RECAPTCHA = 'recaptcha';

    public const DRIVER_TURNSTILE = 'turnstile';

    public const DRIVER_BOTH = 'both';

    /** Local antispam is always available, independently of optional modules. */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Generates a dynamic SVG challenge and an encrypted validation token.
     *
     * @return array{svg: string, token: string}
     */
    public function generateChallenge(): array
    {
        $num1 = random_int(3, 19);
        $num2 = random_int(2, 9);
        $answer = (string) ($num1 + $num2);
        $text = "{$num1} + {$num2} = ?";

        // Generate dynamic noise lines
        $noiseLines = '';
        for ($i = 0; $i < 4; $i++) {
            $x1 = random_int(0, 180);
            $y1 = random_int(5, 45);
            $x2 = random_int(0, 180);
            $y2 = random_int(5, 45);
            $noiseLines .= "<line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"rgba(0, 255, 153, 0.25)\" stroke-width=\"1.5\" />";
        }

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="180" height="50" viewBox="0 0 180 50" style="background:#1A1A1A; border:1px solid #00FF99; border-radius:6px;">
            {$noiseLines}
            <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#00FF99" font-family="'Google Sans Code', monospace" font-size="20" font-weight="bold" letter-spacing="3">
                {$text}
            </text>
        </svg>
        SVG;

        $tokenPayload = json_encode([
            'answer' => $answer,
            'expires_at' => time() + 600, // 10 minutes
        ]);

        $token = Crypt::encryptString((string) $tokenPayload);

        return [
            'svg' => $svg,
            'token' => $token,
        ];
    }

    /**
     * Verifies user response against the encrypted token.
     */
    public function verify(?string $userInput, ?string $token): bool
    {
        if (empty($userInput) || empty($token)) {
            return false;
        }

        try {
            $decrypted = Crypt::decryptString($token);
            $payload = json_decode($decrypted, true);

            if (! is_array($payload) || ! isset($payload['answer'], $payload['expires_at'])) {
                return false;
            }

            $remaining = (int) $payload['expires_at'] - time();
            if ($remaining <= 0) {
                return false; // Expired
            }

            if (! hash_equals(trim(strtolower((string) $payload['answer'])), trim(strtolower($userInput)))) {
                return false;
            }

            // An atomic add consumes this authenticated challenge once. Failed
            // answers preserve it; cache failures are handled as a rejection.
            return Cache::add('captcha:consumed:'.hash('sha256', $token), true, $remaining);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Effective captcha driver: `internal`, `recaptcha`, `turnstile` or `both`.
     *
     * An external driver without its keys silently falls back to the internal
     * challenge, so a misconfiguration never locks a form out.
     */
    public function driver(): string
    {
        $driver = strtolower(trim((string) core_setting('captcha', 'driver', self::DRIVER_INTERNAL)));

        if (! in_array($driver, [self::DRIVER_INTERNAL, self::DRIVER_RECAPTCHA, self::DRIVER_TURNSTILE, self::DRIVER_BOTH], true)) {
            return self::DRIVER_INTERNAL;
        }

        if ($driver === self::DRIVER_BOTH && ! $this->hasExternalKeys()) {
            return self::DRIVER_INTERNAL;
        }

        if ($driver === self::DRIVER_RECAPTCHA && ! $this->hasExternalKeys()) {
            return self::DRIVER_INTERNAL;
        }

        if ($driver === self::DRIVER_TURNSTILE && ! $this->hasTurnstileKeys()) {
            return self::DRIVER_INTERNAL;
        }

        return $driver;
    }

    /**
     * Drivers that must be satisfied for a submission to pass.
     *
     * @return array<int, string>
     */
    public function activeDrivers(): array
    {
        return match ($this->driver()) {
            self::DRIVER_RECAPTCHA => [self::DRIVER_INTERNAL, self::DRIVER_RECAPTCHA],
            self::DRIVER_TURNSTILE => [self::DRIVER_INTERNAL, self::DRIVER_TURNSTILE],
            self::DRIVER_BOTH => [self::DRIVER_INTERNAL, self::DRIVER_RECAPTCHA],
            default => [self::DRIVER_INTERNAL],
        };
    }

    /**
     * Input names that must be present for the active drivers.
     *
     * @return array<int, string>
     */
    public function requiredInputs(): array
    {
        return ['captcha_response'];
    }

    /**
     * Everything the view needs to render the challenge for the active driver.
     *
     * @return array{drivers: array<int, string>, provider: string|null, site_key: string, internal: array{svg: string, token: string}|null}
     */
    public function challenge(): array
    {
        $drivers = $this->activeDrivers();

        $provider = match (true) {
            in_array(self::DRIVER_RECAPTCHA, $drivers, true) => self::DRIVER_RECAPTCHA,
            in_array(self::DRIVER_TURNSTILE, $drivers, true) => self::DRIVER_TURNSTILE,
            default => null,
        };

        return [
            'drivers' => $drivers,
            'provider' => $provider,
            'site_key' => $this->siteKey(),
            'internal' => in_array(self::DRIVER_INTERNAL, $drivers, true) ? $this->generateChallenge() : null,
        ];
    }

    /**
     * Server-side verification for the active drivers.
     *
     * With `both` the submission passes when **any** active driver verifies, so
     * a provider outage never blocks legitimate users.
     *
     * @param  array<string, mixed>  $input
     */
    public function verifyRequest(array $input, ?string $ip = null): bool
    {
        foreach ($this->activeDrivers() as $driver) {
            if ($driver === self::DRIVER_INTERNAL) {
                $response = $input['captcha_response'] ?? null;
                $token = $input['_captcha_token'] ?? null;

                if ($this->verify(is_string($response) ? $response : null, is_string($token) ? $token : null)) {
                    return true;
                }

                continue;
            }

            $token = $input['g-recaptcha-response'] ?? $input['cf-turnstile-response'] ?? null;

            if (is_string($token) && $token !== '' && $this->verifyExternal($driver, $token, $ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a widget token against Google reCAPTCHA / Cloudflare Turnstile.
     */
    public function verifyExternal(string $driver, string $token, ?string $ip = null): bool
    {
        $secret = $this->secretKey();

        if (! $this->isEnabled() || ! app(LegalPageService::class)->canCollect('captcha', true) || ! app(CookieConsentService::class)->hasConsent('external') || $secret === '' || $token === '') {
            return false;
        }

        $endpoint = $driver === self::DRIVER_TURNSTILE
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            : 'https://www.google.com/recaptcha/api/siteverify';

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post($endpoint, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    // The optional remote IP is deliberately omitted.
                ]));
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $response->json();

        if (($payload['success'] ?? false) !== true) {
            return false;
        }

        // reCAPTCHA v3 is score-based.
        if ($driver === self::DRIVER_RECAPTCHA) {
            $score = (float) ($payload['score'] ?? 1.0);
            $minimum = (float) core_setting('captcha', 'min_score', 0.5);

            return $score >= $minimum;
        }

        return true;
    }

    /**
     * Third-party origins required by the active drivers (for the CSP).
     *
     * Scripts are covered by the CSP nonce + `strict-dynamic`; only frames and
     * network calls need host allowlists.
     *
     * @return array{frame: array<int, string>, connect: array<int, string>}
     */
    public function cspOrigins(): array
    {
        $drivers = $this->activeDrivers();
        $frame = [];
        $connect = [];

        if (in_array(self::DRIVER_RECAPTCHA, $drivers, true)) {
            $frame[] = 'https://www.google.com';
            $connect[] = 'https://www.google.com';
        }

        if (in_array(self::DRIVER_TURNSTILE, $drivers, true)) {
            $frame[] = 'https://challenges.cloudflare.com';
            $connect[] = 'https://challenges.cloudflare.com';
        }

        return [
            'frame' => array_values(array_unique($frame)),
            'connect' => array_values(array_unique($connect)),
        ];
    }

    /**
     * Returns the configured public site key if an external provider is active.
     */
    public function siteKey(): string
    {
        return (string) core_setting('captcha', 'site_key', '');
    }

    private function secretKey(): string
    {
        return (string) core_setting('captcha', 'secret_key', '');
    }

    private function hasExternalKeys(): bool
    {
        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    private function hasTurnstileKeys(): bool
    {
        return $this->hasExternalKeys();
    }

    public function connectOrigins(): array
    {
        return $this->isEnabled() ? array_values($this->cspOrigins()['connect']) : [];
    }

    public function frameOrigins(): array
    {
        return $this->isEnabled() ? array_values($this->cspOrigins()['frame']) : [];
    }
}

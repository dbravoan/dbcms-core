<?php

declare(strict_types=1);

namespace DbCms\Core\Captcha\Services;

use DbCms\Core\Security\Contracts\CookieDisclosureContributorInterface;
use DbCms\Core\Services\Privacy\LegalPageService;

final class CaptchaCookieDisclosure implements CookieDisclosureContributorInterface
{
    public function __construct(private readonly CaptchaService $captcha) {}

    public function revisionData(): array
    {
        return $this->categories('en') === [] ? [] : ['driver' => $this->captcha->driver(), 'site_key' => $this->captcha->siteKey()];
    }

    public function categories(string $locale): array
    {
        if (! app(LegalPageService::class)->canCollect('captcha', true) || ! $this->captcha->isEnabled() || $this->captcha->driver() === CaptchaService::DRIVER_INTERNAL) {
            return [];
        }
        $provider = $this->captcha->driver() === CaptchaService::DRIVER_TURNSTILE ? 'Cloudflare Turnstile' : 'Google reCAPTCHA';

        return ['external' => [
            'key' => 'external', 'title' => $locale === 'ca' ? 'Serveis externs' : ($locale === 'es' ? 'Servicios externos' : 'External services'),
            'description' => $provider.($locale === 'ca' ? ' verifica sol·licituds i rep dades de connexió només amb permís. Sempre podeu utilitzar l’operació matemàtica local.' : ($locale === 'es' ? ' verifica solicitudes y recibe datos de conexión solo con permiso. Siempre puede utilizar la operación matemática local.' : ' verifies requests and receives connection data only with permission. The local calculation remains available.')),
            'required' => false, 'default' => false, 'cookies' => [],
        ]];
    }
}

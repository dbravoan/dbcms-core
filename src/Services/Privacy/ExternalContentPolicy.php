<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Privacy;

use DbCms\Core\Security\Contracts\ContentSecurityPolicyContributorInterface;
use DbCms\Core\Services\Cms\CookieConsentService;
use DomainException;
use Illuminate\Support\Facades\Vite;

/** Explicit project declarations are the boundary for executable external embeds. */
final class ExternalContentPolicy implements ContentSecurityPolicyContributorInterface
{
    /** @return array<string, array{provider:string, purpose:string, origins:list<string>}> */
    public function services(): array
    {
        return $this->declarations('external_services', 'external_');
    }

    /** @return array<string, array{provider:string, purpose:string, origins:list<string>}> */
    public function assetServices(): array
    {
        return $this->declarations('asset_services', 'assets_');
    }

    /** @return array<string, array{provider:string, purpose:string, origins:list<string>}> */
    private function declarations(string $setting, string $ownerPrefix): array
    {
        $configured = config('privacy.'.$setting, []);
        if (! is_array($configured)) {
            throw new DomainException('privacy.'.$setting.' must be a service map.');
        }
        $services = [];
        foreach ($configured as $id => $service) {
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1
                || ! is_array($service) || ! is_string($service['provider'] ?? null)
                || trim($service['provider']) === '' || ! is_string($service['purpose'] ?? null)
                || trim($service['purpose']) === '' || ! is_array($service['origins'] ?? null)
                || $service['origins'] === [] || ! array_is_list($service['origins'])) {
                throw new DomainException('External services need an identifier, provider, purpose and exact HTTPS origins.');
            }
            $origins = [];
            foreach ($service['origins'] as $origin) {
                if (! is_string($origin) || $this->origin($origin) !== $origin) {
                    throw new DomainException('External service origins must be exact HTTPS origins without paths.');
                }
                $origins[] = $origin;
            }
            if (! app(LegalPageService::class)->canCollect($ownerPrefix.$id, true)) {
                continue;
            }
            sort($origins);
            $services[$id] = ['provider' => trim($service['provider']), 'purpose' => trim($service['purpose']), 'origins' => array_values(array_unique($origins))];
        }
        ksort($services);

        return $services;
    }

    /** Browser media must use reviewed necessary hosting or a current optional grant.
     *
     * @return list<string>
     */
    public function assetOrigins(): array
    {
        $services = $this->assetServices();
        $optional = $this->services();
        if ($optional !== [] && app(CookieConsentService::class)->hasConsent('external')) {
            $services = array_merge(array_values($services), array_values($optional));
        }

        return array_values(array_unique(array_merge([], ...array_column($services, 'origins'))));
    }

    public function permits(string $service, string $url): bool
    {
        $origin = $this->origin($url);

        return $origin !== null && in_array($origin, $this->services()[$service]['origins'] ?? [], true);
    }

    public function render(string $html, string $service): string
    {
        $declaration = $this->services()[$service] ?? null;
        if ($declaration === null) {
            return '<p role="status">'.htmlspecialchars((string) __('privacy.external_unconfigured'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }

        return view('components.cms.consent-content', ['html' => $html, 'category' => 'external', 'service' => $declaration])->render();
    }

    public function document(string $html, string $service): string
    {
        $origins = implode(' ', $this->services()[$service]['origins'] ?? []);
        $policy = "default-src 'none'; script-src 'unsafe-inline' {$origins}; style-src 'unsafe-inline' {$origins}; img-src data: {$origins}; media-src {$origins}; connect-src {$origins}; frame-src {$origins}; form-action 'none'; base-uri 'none'";

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"></head><body>'.$html.'</body></html>', LIBXML_NONET);
            $nonce = Vite::cspNonce();
            if (is_string($nonce) && $nonce !== '') {
                foreach ($document->getElementsByTagName('script') as $script) {
                    $script->setAttribute('nonce', $nonce);
                }
            }
            $rendered = $document->saveHTML();
            if (! is_string($rendered)) {
                throw new DomainException('The embedded document could not be rendered safely.');
            }

            return $rendered;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return list<string> */
    public function connectOrigins(): array
    {
        // Optional receivers are only allowed while a current external-content
        // consent receipt exists. The consent client still controls insertion.
        if (! app(CookieConsentService::class)->hasConsent('external')) {
            return [];
        }

        return array_values(array_unique(array_merge([], ...array_column($this->services(), 'origins'))));
    }

    /** @return list<string> */
    public function frameOrigins(): array
    {
        return $this->connectOrigins();
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || preg_match('/^[a-zA-Z0-9.-]+$/D', $parts['host']) !== 1) {
            return null;
        }

        return 'https://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}

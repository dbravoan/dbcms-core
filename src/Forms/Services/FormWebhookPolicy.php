<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Media\RemoteMediaUrlPolicy;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\Http;

/** Server configuration and reviewed processing are the boundary for outbound data. */
final class FormWebhookPolicy
{
    public function available(string $key): bool
    {
        return $this->configuration($key) !== null;
    }

    /** @return list<string> */
    public function targets(): array
    {
        return array_values(array_filter(array_keys((array) config('forms_delivery.webhooks', [])), fn (string $key): bool => $this->available($key)));
    }

    public function revision(string $key): ?string
    {
        $config = $this->configuration($key);
        if ($config === null) {
            return null;
        }
        unset($config['token']);

        return hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
    }

    /** Returns false when reviewed configuration or consent was withdrawn.
     * @param  array<string,mixed>  $definition
     * @param  array<string,mixed>  $payload
     */
    public function send(array $definition, array $payload, string $idempotencyKey): bool
    {
        $key = (string) ($definition['target'] ?? '');
        $config = $this->configuration($key);
        if ($config === null || $this->revision($key) !== ($definition['configuration_revision'] ?? null)) {
            return false;
        }
        $purpose = $config['purpose'] ?? null;
        if (is_string($purpose) && ! app(CookieConsentService::class)->hasConsentForReceipt($purpose, (string) ($definition['consent_receipt_id'] ?? ''))) {
            return false;
        }
        $destination = app(RemoteMediaUrlPolicy::class)->resolve($config['url']);
        $body = array_intersect_key($payload, array_flip($config['fields']));
        // A fixed resolve entry closes the DNS validation/connection race.
        $address = $destination['addresses'][0];
        $address = str_contains($address, ':') ? '['.$address.']' : $address;
        $request = Http::asJson()->acceptJson()->withoutRedirecting()->connectTimeout(3)->timeout(10)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [$destination['host'].':'.$destination['port'].':'.$address]], 'proxy' => '']);
        if (is_string($config['token'] ?? null) && $config['token'] !== '') {
            $request = $request->withToken($config['token']);
        }
        $response = $request->post($config['url'], ['fields' => $body]);
        if (! $response->successful()) {
            throw new \RuntimeException('Form webhook delivery failed.');
        }

        return true;
    }

    /** @return array{url:string,fields:list<string>,purpose:string|null,token:string,processing_revision:string}|null */
    private function configuration(string $key): ?array
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,49}$/D', $key) !== 1) {
            return null;
        }
        $config = config('forms_delivery.webhooks.'.$key);
        if (! is_array($config) || ! is_string($config['url'] ?? null)) {
            return null;
        }
        $parts = parse_url($config['url']);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)
            || ! is_array($config['fields'] ?? null) || $config['fields'] === [] || ! array_is_list($config['fields'])
            || array_filter($config['fields'], static fn (mixed $field): bool => ! is_string($field) || preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $field) !== 1) !== []) {
            return null;
        }
        $purpose = $config['purpose'] ?? null;
        if ($purpose !== null && ! in_array($purpose, ['analytics', 'marketing', 'external'], true)) {
            return null;
        }
        if (! app(LegalPageService::class)->canCollect('forms_webhook_'.$key)) {
            return null;
        }
        $policy = app(LegalPageService::class)->requireProcessing('forms_webhook_'.$key);

        return ['url' => $config['url'], 'fields' => $config['fields'], 'purpose' => $purpose,
            'token' => is_string($config['token'] ?? null) ? $config['token'] : '',
            // Processing changes invalidate pending deliveries too.
            'processing_revision' => hash('sha256', json_encode($policy, JSON_THROW_ON_ERROR))];
    }
}

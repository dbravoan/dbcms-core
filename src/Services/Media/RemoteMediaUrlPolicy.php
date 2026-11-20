<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use InvalidArgumentException;

final class RemoteMediaUrlPolicy
{
    /** @return array{host: string, port: int, addresses: list<string>} */
    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Media URL must be an HTTP(S) URL without credentials.');
        }
        $host = trim((string) $parts['host'], '[]');
        $port = (int) ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Media URL port is not permitted.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->addresses($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('Media hostname does not resolve to a public address.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                || $this->isNonPublicRange($address)
                || str_starts_with($address, '198.18.') || str_starts_with($address, '198.19.')) {
                throw new InvalidArgumentException('Media destination must be a public Internet address.');
            }
        }

        return ['host' => $host, 'port' => $port, 'addresses' => $addresses];
    }

    private function isNonPublicRange(string $address): bool
    {
        if (str_contains($address, ':')) {
            $binary = inet_pton($address);

            return $binary === false || (ord($binary[0]) & 0xE0) !== 0x20
                || substr($binary, 0, 4) === hex2bin('20010000') || substr($binary, 0, 2) === hex2bin('2002');
        }
        $octets = array_map('intval', explode('.', $address));

        return ($octets[0] === 100 && $octets[1] >= 64 && $octets[1] <= 127) || $octets[0] >= 224;
    }

    /** @return list<string> */
    private function addresses(string $host): array
    {
        if (! preg_match('/^[a-z][a-z0-9.-]*\.[a-z]{2,}$/i', $host)) {
            return [];
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}

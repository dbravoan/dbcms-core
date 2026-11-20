<?php

declare(strict_types=1);

namespace DbCms\Core\Security\Contracts;

interface CookieDisclosureContributorInterface
{
    /** @return array<string, array{key: string, title: string, description: string, required: bool, default: bool, cookies: array<int, array{name: string, provider: string, purpose: string, duration: string}>}> */
    public function categories(string $locale): array;

    /** Effective non-secret destination identifiers; [] when the integration is unavailable.
     * @return array<string,string>
     */
    public function revisionData(): array;
}

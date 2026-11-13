<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Security\Contracts\CookieDisclosureContributorInterface;
use DbCms\Core\Services\Privacy\LegalPageService;

/** Optional visitor deadlines are disclosed and reviewed before storage is offered. */
final class BlockStorageDisclosure implements CookieDisclosureContributorInterface
{
    public function available(): bool
    {
        return config('cms.blocks.countdown_storage.enabled') === true
            && in_array(config('cms.blocks.countdown_storage.purpose'), ['preferences', 'analytics', 'marketing', 'external'], true)
            && app(LegalPageService::class)->canCollect('block_countdowns', true);
    }

    public function revisionData(): array
    {
        return $this->available() ? ['block_countdowns' => hash('sha256', json_encode([
            config('cms.blocks.countdown_storage.purpose'), $this->maxDays(),
        ], JSON_THROW_ON_ERROR))] : [];
    }

    public function categories(string $locale): array
    {
        if (! $this->available()) {
            return [];
        }
        $purpose = (string) config('cms.blocks.countdown_storage.purpose');
        $description = $locale === 'es'
            ? 'Conserva la fecha de finalización de las cuentas atrás por visitante cuando se autoriza su almacenamiento.'
            : 'Keeps visitor countdown deadlines when storage is authorized.';

        return [$purpose => ['key' => $purpose, 'title' => ucfirst($purpose), 'description' => $description,
            'required' => false, 'default' => false, 'cookies' => [[
                'name' => 'dbcms.countdown.v1.*', 'provider' => 'dbCMS (Web Storage)', 'purpose' => $description,
                'duration' => (string) $this->maxDays().($locale === 'es' ? ' días como máximo' : ' days maximum'),
            ]]]];
    }

    public function maxDays(): int
    {
        return max(1, min(365, (int) config('cms.blocks.countdown_storage.max_days', 30)));
    }
}

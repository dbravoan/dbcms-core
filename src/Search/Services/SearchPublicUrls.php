<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Services;

use DbCms\Core\Content\Contracts\PublicUrlContributorInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;

final class SearchPublicUrls implements PublicUrlContributorInterface
{
    public function __construct(private readonly LocalizationServiceInterface $localization) {}

    public function ownerModule(): ?string
    {
        return null;
    }

    public function urls(Market $market, string $locale): array
    {
        if (! (bool) core_setting('search', 'is_indexable', false)) {
            return [];
        }
        $prefix = $this->localization->getModulePrefix('search', $locale);

        return [$this->localization->url($prefix, $market, $locale) => 'General Search'];
    }
}

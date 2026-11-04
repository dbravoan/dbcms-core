<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\Market;

interface SeoServiceInterface
{
    /**
     * Generates a valid XML sitemap string.
     */
    public function generateSitemapXml(?Market $market = null, ?string $locale = null, ?int $part = null): string;
}

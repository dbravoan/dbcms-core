<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\Market;

interface GeoServiceInterface
{
    /**
     * Generates standard /llms.txt content summarizing site capabilities for AI crawlers.
     */
    public function generateLlmsTxt(?Market $market = null, ?string $locale = null): string;

    /**
     * Generates extended /llms-full.txt content with full semantic corpus.
     */
    public function generateLlmsFullTxt(?Market $market = null, ?string $locale = null): string;
}

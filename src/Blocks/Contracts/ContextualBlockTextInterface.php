<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

/** Public text may expand declared reusable content, without executing a Blade view. */
interface ContextualBlockTextInterface
{
    /** @param array<string,mixed> $payload */
    public function text(array $payload, Market $market, string $locale, bool $includeWidgets = true): string;
}

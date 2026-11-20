<?php

declare(strict_types=1);

namespace Acme\Visuals;

use DbCms\Core\Blocks\Types\RichTextBlock;
use DbCms\Core\Models\Market;

final class StatisticBlock extends RichTextBlock
{
    public static int $renders = 0;

    public function getType(): string
    {
        return 'acme-statistic';
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        self::$renders++;

        return 'Third-party statistic';
    }
}

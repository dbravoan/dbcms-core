<?php

declare(strict_types=1);

namespace Fixture\Blocks;

final class ExternalMetric extends ExternalBlock
{
    public function getType(): string
    {
        return 'fixture_metric';
    }
}

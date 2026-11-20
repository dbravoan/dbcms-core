<?php

declare(strict_types=1);

namespace Fixture\Blocks;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;

class ExternalBlock extends AbstractBlockType
{
    public function getType(): string
    {
        return 'fixture_chart';
    }

    public function getName(): string
    {
        return 'External fixture';
    }

    public function getIcon(): string
    {
        return 'fa-chart-line';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDefaultPayload(): array
    {
        return ['title' => ''];
    }

    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        return '<p>'.e($payload['title'] ?? '').'</p>';
    }
}

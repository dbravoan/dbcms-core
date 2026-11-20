<?php

declare(strict_types=1);

use DbCms\Core\Blocks\ChartRendererService;
use DbCms\Core\Blocks\Types\ChartsBlock;

it('renders the editor label payload as a line chart', function (): void {
    $block = new ChartsBlock(new ChartRendererService);
    $payload = $block->getDefaultPayload();
    $payload['chart_type'] = 'line';

    expect($block->render($payload))->toContain('Velocidad de Carga', 'Conversión de Leads');
});

it('escapes line chart labels', function (): void {
    $html = (new ChartRendererService)->renderLine([
        ['label' => '<script>alert(1)</script>', 'value' => 1],
        ['label' => '<img src=x onerror=alert(1)>', 'value' => 2],
    ]);

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;', '&lt;img src=x onerror=alert(1)&gt;')
        ->not->toContain('<script>', '<img src=x');
});

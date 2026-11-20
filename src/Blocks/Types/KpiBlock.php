<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;

class KpiBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.label', 'items.*.description'];
    }

    public function getType(): string
    {
        return 'kpi';
    }

    public function getName(): string
    {
        return 'Métricas y Contadores KPI';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-chart-column';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Contadores numéricos de alto impacto para métricas de rendimiento y estadísticas.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Métricas Clave de Rendimiento',
            'items' => [
                ['value' => '99.9%', 'label' => 'Disponibilidad Garantizada'],
                ['value' => '< 50ms', 'label' => 'Latencia de Respuesta'],
                ['value' => '100%', 'label' => 'Puntuación Lighthouse'],
                ['value' => '0', 'label' => 'Vulnerabilidades Críticas'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Key performance metrics',
            'items' => [
                ['value' => '99.9%', 'label' => 'Guaranteed uptime'],
                ['value' => '< 50ms', 'label' => 'Response latency'],
                ['value' => '100%', 'label' => 'Lighthouse score'],
                ['value' => '0', 'label' => 'Critical vulnerabilities'],
            ],
        ]);
    }

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $schema['title']['description'] = 'Título opcional de la cuadrícula de métricas.';
        $schema['items']['label'] = 'Indicadores';
        $schema['items']['description'] = 'Cada fila es un contador o KPI.';
        $schema['items']['fields']['description'] = ['type' => 'textarea', 'label' => 'Descripción', 'description' => 'Texto opcional bajo el indicador.', 'rules' => ['nullable', 'string', 'max:1000']];
        $schema['items']['fields']['value']['label'] = 'Valor visible';
        $schema['items']['fields']['value']['description'] = 'Puede incluir símbolos, por ejemplo 99,9 % o < 50 ms.';
        $schema['items']['fields']['label']['label'] = 'Etiqueta del indicador';

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $items = (array) ($payload['items'] ?? []);

        $headerHtml = ! empty($title) ? "<div class=\"text-center max-w-2xl mx-auto mb-8\"><h2 class=\"text-2xl font-bold cms-text flex items-center justify-center gap-2\"><span class=\"cms-accent\">&gt;_</span> {$title}</h2></div>" : '';

        $countersHtml = '';
        foreach ($items as $item) {
            $val = htmlspecialchars((string) ($item['value'] ?? ''));
            $lbl = htmlspecialchars((string) ($item['label'] ?? ''));
            $description = htmlspecialchars((string) ($item['description'] ?? ''));
            $descriptionHtml = $description !== '' ? '<p class="mt-2 text-xs cms-text-muted">'.$description.'</p>' : '';

            $countersHtml .= <<<HTML
            <div class="text-center p-6 rounded-2xl cms-surface-2 border cms-border cms-hover-border-accent transition-colors">
                <div class="text-3xl sm:text-4xl font-black cms-accent font-mono mb-2" data-scroll-magic="scale">{$val}</div>
                <div class="text-xs cms-text-muted uppercase font-mono tracking-wider">{$lbl}</div>
                {$descriptionHtml}
            </div>
            HTML;
        }

        $gridCols = count($items) === 2 ? 'grid-cols-2' : (count($items) === 3 ? 'grid-cols-1 md:grid-cols-3' : 'grid-cols-2 lg:grid-cols-4');

        return <<<HTML
        <div class="space-y-6 w-full font-mono">
            {$headerHtml}
            <div class="grid {$gridCols} gap-4 sm:gap-6">
                {$countersHtml}
            </div>
        </div>
        HTML;
    }
}

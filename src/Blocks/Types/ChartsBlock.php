<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\ChartRendererService;
use DbCms\Core\Blocks\Contracts\TranslatableBlockInterface;
use DbCms\Core\Models\Market;

class ChartsBlock extends AbstractBlockType implements TranslatableBlockInterface
{
    protected ChartRendererService $renderer;

    public function __construct(
        ?ChartRendererService $renderer = null
    ) {
        $this->renderer = $renderer ?? app(ChartRendererService::class);
    }

    public function getType(): string
    {
        return 'charts';
    }

    public function getName(): string
    {
        return 'Gráficas y Visualización de Datos';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-chart-pie';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Gráficas animadas de barras, líneas continuas, anillos circulares y contadores numéricos.';
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title', 'items.*.label', 'donut_label', 'donut_subtext', 'metric_label', 'metric_description'];
    }

    public function getEditorSchema(): array
    {
        $fields = parent::getEditorSchema();
        $fields['title']['description'] = 'Título opcional que se muestra sobre la visualización.';
        $fields['chart_type'] = [
            'type' => 'select',
            'label' => 'Tipo de gráfica',
            'options' => ['bar' => 'Barras', 'line' => 'Línea', 'donut' => 'Anillo', 'metric' => 'Métrica destacada'],
            'default' => 'bar',
            'description' => 'Elige la visualización y se mostrarán solo los campos que necesita.',
        ];
        $fields['items']['label'] = 'Valores de la gráfica';
        $fields['items']['description'] = 'Cada fila es una barra o un punto de la línea.';
        $fields['items']['show_when'] = ['field' => 'chart_type', 'in' => ['bar', 'line']];
        $fields['items']['fields']['label']['label'] = 'Etiqueta';
        $fields['items']['fields']['value']['label'] = 'Valor';
        $fields['items']['fields']['value']['description'] = 'Valor numérico que determina la altura o posición.';
        $fields['items']['fields']['color']['description'] = 'Color CSS, por ejemplo #38BDF8 o var(--cms-accent).';
        $fields['items']['fields']['unit']['description'] = 'Sufijo visible, por ejemplo % o €.';
        $fields['donut_color'] = [
            'type' => 'text',
            'label' => 'Color del anillo',
            'default' => 'var(--cms-accent)',
            'description' => 'Color CSS del anillo, por ejemplo #38BDF8 o var(--cms-accent).',
            'show_when' => ['field' => 'chart_type', 'equals' => 'donut'],
        ];
        $fields['donut_percentage'] = [
            'type' => 'number',
            'label' => 'Porcentaje del anillo',
            'default' => 88,
            'min' => 0,
            'max' => 100,
            'description' => 'Valor entre 0 y 100 que rellena el anillo.',
            'show_when' => ['field' => 'chart_type', 'equals' => 'donut'],
        ];
        $fields['donut_label']['description'] = 'Texto central del anillo.';
        $fields['donut_label']['show_when'] = ['field' => 'chart_type', 'equals' => 'donut'];
        $fields['donut_subtext']['description'] = 'Texto secundario bajo el anillo.';
        $fields['donut_subtext']['show_when'] = ['field' => 'chart_type', 'equals' => 'donut'];
        $fields['metric_value']['description'] = 'Valor grande que se muestra en la métrica.';
        $fields['metric_value']['show_when'] = ['field' => 'chart_type', 'equals' => 'metric'];
        $fields['metric_label']['description'] = 'Etiqueta de la métrica.';
        $fields['metric_label']['show_when'] = ['field' => 'chart_type', 'equals' => 'metric'];
        $fields['metric_description']['description'] = 'Texto de apoyo de la métrica.';
        $fields['metric_description']['show_when'] = ['field' => 'chart_type', 'equals' => 'metric'];

        return $fields;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Métricas de Desempeño y Rendimiento',
            'chart_type' => 'bar', // 'bar', 'donut', 'line', 'metric'
            'items' => [
                ['label' => 'Velocidad de Carga', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                ['label' => 'SEO & GEO Indexing', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                ['label' => 'Conversión de Leads', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
            ],
            'donut_color' => 'var(--cms-accent)',
            'donut_percentage' => 88,
            'donut_label' => 'Tasa de Eficiencia Operativa',
            'donut_subtext' => 'Reducción de coste perimetral',
            'metric_value' => '99.99%',
            'metric_label' => 'Disponibilidad de Servicio',
            'metric_description' => 'Infraestructura global con tolerancia a fallos',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Performance metrics',
            'items' => [
                ['label' => 'Page speed', 'value' => 98, 'color' => 'var(--cms-accent)', 'unit' => '%'],
                ['label' => 'SEO & GEO indexing', 'value' => 95, 'color' => '#38BDF8', 'unit' => '%'],
                ['label' => 'Lead conversion', 'value' => 84, 'color' => '#F59E0B', 'unit' => '%'],
            ],
            'donut_label' => 'Operational efficiency rate',
            'donut_subtext' => 'Edge cost reduction',
            'metric_label' => 'Service availability',
            'metric_description' => 'Global fault-tolerant infrastructure',
        ]);
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $type = (string) ($payload['chart_type'] ?? 'bar');
        $title = (string) ($payload['title'] ?? '');

        $renderedChart = match ($type) {
            'donut' => $this->renderer->renderDonut(
                (float) ($payload['donut_percentage'] ?? 85),
                (string) ($payload['donut_label'] ?? 'Eficiencia'),
                (string) ($payload['donut_subtext'] ?? ''),
                ['color' => $payload['donut_color'] ?? $payload['color'] ?? 'var(--cms-accent)']
            ),
            'line' => $this->renderer->renderLine(
                (array) ($payload['items'] ?? [
                    ['label' => 'Mes 1', 'value' => 20],
                    ['label' => 'Mes 2', 'value' => 45],
                    ['label' => 'Mes 3', 'value' => 78],
                    ['label' => 'Mes 4', 'value' => 95],
                ]),
                ['title' => $title]
            ),
            'metric' => $this->renderer->renderMetric(
                (string) ($payload['metric_value'] ?? '100%'),
                (string) ($payload['metric_label'] ?? 'Métrica Clave'),
                (string) ($payload['metric_description'] ?? '')
            ),
            default => $this->renderer->renderBar(
                (array) ($payload['items'] ?? []),
                ['title' => $title]
            ),
        };

        if ($type === 'metric' || $type === 'donut') {
            return <<<HTML
            <div class="max-w-xl mx-auto w-full">
                {$renderedChart}
            </div>
            HTML;
        }

        return $renderedChart;
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\ChartRendererService;
use DbCms\Core\Models\Market;

class RoiBlock extends AbstractBlockType
{
    public function __construct(private readonly ChartRendererService $chartRenderer) {}

    public function getTranslationPaths(): array
    {
        return ['title', 'description'];
    }

    public function getType(): string
    {
        return 'roi';
    }

    public function getName(): string
    {
        return 'Calculadora y Retorno de Inversión (ROI)';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-chart-line';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Sección de cálculo de retorno comercial y ahorro proyectado que compone e integra primitivas de gráficas.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Calculadora de Retorno de Inversión (ROI)',
            'description' => 'Comprueba el impacto directo en reducción de costes de infraestructura y aumento de conversión con la arquitectura perimetral de '.config('cms.name').'.',
            'initial_investment' => 12000,
            'annual_savings' => 38000,
            'project_years' => 3,
            'current_solution_cost' => 50000,
            'optimized_solution_cost' => 12000,
            'payback_months' => 4,
            'currency_symbol' => '€',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Return on investment (ROI) calculator',
            'description' => 'See the direct impact on infrastructure costs and conversion with the edge architecture of '.config('cms.name').'.',
        ]);
    }

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $schema['title']['description'] = 'Título visible de la calculadora.';
        $schema['description']['description'] = 'Explica qué calcula el bloque antes de mostrar los resultados.';
        $schema['initial_investment'] = [
            'type' => 'number',
            'label' => 'Inversión inicial',
            'default' => 12000,
            'min' => 0,
            'step' => 100,
            'description' => 'Coste inicial que se recupera con el ahorro estimado.',
        ];
        $schema['annual_savings'] = [
            'type' => 'number',
            'label' => 'Ahorro anual',
            'default' => 38000,
            'min' => 0,
            'step' => 100,
            'description' => 'Ahorro estimado cada año.',
        ];
        $schema['project_years'] = [
            'type' => 'integer',
            'label' => 'Años del proyecto',
            'default' => 3,
            'min' => 1,
            'max' => 20,
            'description' => 'Periodo usado para calcular el retorno total.',
        ];
        $schema['current_solution_cost'] = [
            'type' => 'number',
            'label' => 'Coste actual',
            'default' => 50000,
            'min' => 0,
            'step' => 100,
            'description' => 'Coste anual de la solución actual.',
        ];
        $schema['optimized_solution_cost'] = [
            'type' => 'number',
            'label' => 'Coste optimizado',
            'default' => 12000,
            'min' => 0,
            'step' => 100,
            'description' => 'Coste anual estimado con la nueva arquitectura.',
        ];
        $schema['payback_months'] = [
            'type' => 'integer',
            'label' => 'Meses de amortización',
            'default' => 4,
            'min' => 1,
            'max' => 120,
            'description' => 'Meses estimados para recuperar la inversión inicial.',
        ];
        $schema['currency_symbol'] = ['type' => 'text', 'label' => 'Símbolo de moneda', 'default' => '€', 'description' => 'Símbolo que acompaña a los importes.'];

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? 'Calculadora de Retorno de Inversión (ROI)'));
        $desc = htmlspecialchars((string) ($payload['description'] ?? ''));
        $currency = htmlspecialchars((string) ($payload['currency_symbol'] ?? '€'));

        $investment = (float) ($payload['initial_investment'] ?? 12000);
        $annualSavings = (float) ($payload['annual_savings'] ?? 38000);
        $years = (int) ($payload['project_years'] ?? 3);
        $currentCost = (float) ($payload['current_solution_cost'] ?? 50000);
        $optimizedCost = (float) ($payload['optimized_solution_cost'] ?? 12000);
        $paybackMonths = (int) ($payload['payback_months'] ?? 4);

        if ($investment <= 0) {
            $investment = 1;
        }

        $totalSavings = ($annualSavings * $years) - $investment;
        $roiPercentage = round(($totalSavings / $investment) * 100);
        $efficiencyPct = $currentCost > 0 ? round((($currentCost - $optimizedCost) / $currentCost) * 100) : 75;

        $donutHtml = $this->chartRenderer->renderDonut(
            $efficiencyPct,
            'Eficiencia Operativa',
            'Reducción de coste perimetral',
            ['color' => 'var(--cms-accent)', 'size' => 170]
        );

        $barHtml = $this->chartRenderer->renderBar([
            ['label' => 'Coste Actual de Infraestructura', 'value' => $currentCost, 'color' => '#EF4444', 'unit' => " {$currency}/año"],
            ['label' => 'Coste Optimizado con '.config('cms.name'), 'value' => $optimizedCost, 'color' => 'var(--cms-accent)', 'unit' => " {$currency}/año"],
        ], ['title' => 'Comparativa Anual de Costes']);

        $metricPayback = $this->chartRenderer->renderMetric(
            "{$paybackMonths} Meses",
            'Tiempo de Amortización (Payback)',
            'Recuperación de la inversión inicial estimada'
        );

        $metricRoi = $this->chartRenderer->renderMetric(
            "+{$roiPercentage}%",
            'Retorno Neto Proyectado (ROI)',
            "Retorno total estimado en {$years} años"
        );

        return <<<HTML
        <div class="space-y-8 font-mono">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <h2 class="text-2xl sm:text-3xl font-black cms-text flex items-center justify-center gap-2">
                    <span class="cms-accent">&gt;_</span> {$title}
                </h2>
                <p class="text-xs cms-text-muted">{$desc}</p>
            </div>

            <!-- Key Financial KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {$metricRoi}
                {$metricPayback}
            </div>

            <!-- Visual Charts Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                <div class="lg:col-span-2 w-full">
                    {$barHtml}
                </div>
                <div class="w-full">
                    {$donutHtml}
                </div>
            </div>
        </div>
        HTML;
    }
}

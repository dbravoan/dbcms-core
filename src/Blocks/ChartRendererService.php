<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

class ChartRendererService
{
    /**
     * Renders an animated bar chart comparison using responsive SVG/CSS.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $options
     */
    public function renderBar(array $data, array $options = []): string
    {
        if (empty($data)) {
            return '';
        }

        $title = ! empty($options['title']) ? htmlspecialchars((string) $options['title']) : '';
        $height = (int) ($options['height'] ?? 240);
        $maxValue = max(array_map(fn ($item) => (float) ($item['value'] ?? 0), $data));
        if ($maxValue <= 0) {
            $maxValue = 100;
        }

        $barsHtml = '';
        foreach ($data as $item) {
            $lbl = htmlspecialchars((string) ($item['label'] ?? ''));
            $val = (float) ($item['value'] ?? 0);
            $color = htmlspecialchars((string) ($item['color'] ?? 'var(--cms-accent)'));
            $unit = htmlspecialchars((string) ($item['unit'] ?? ''));
            $pct = round(($val / $maxValue) * 100, 1);

            $barsHtml .= <<<HTML
            <div class="space-y-1 font-mono text-xs">
                <div class="flex items-center justify-between cms-text-muted">
                    <span class="font-bold cms-text">{$lbl}</span>
                    <span class="cms-accent font-bold">{$val}{$unit}</span>
                </div>
                <div class="w-full cms-surface-2 border cms-border rounded-lg h-5 overflow-hidden p-0.5">
                    <div class="h-full rounded-md transition-all duration-1000 ease-out"
                         style="width: {$pct}%; background-color: {$color};"
                         data-scroll-magic="scale-x"></div>
                </div>
            </div>
            HTML;
        }

        $header = ! empty($title) ? "<h4 class=\"text-sm font-bold cms-text font-mono mb-4 flex items-center gap-2\"><span class=\"cms-accent\">&gt;</span> {$title}</h4>" : '';

        return <<<HTML
        <div class="p-5 rounded-2xl cms-surface-2 border cms-border space-y-4 w-full">
            {$header}
            <div class="space-y-3">
                {$barsHtml}
            </div>
        </div>
        HTML;
    }

    /**
     * Renders an animated SVG Donut / Radial Progress ring.
     *
     * @param  array<string, mixed>  $options
     */
    public function renderDonut(float|int $percentage, string $label = '', string $subtext = '', array $options = []): string
    {
        $pct = max(0, min(100, (float) $percentage));
        $lbl = htmlspecialchars($label);
        $sub = htmlspecialchars($subtext);
        $color = htmlspecialchars((string) ($options['color'] ?? 'var(--cms-accent)'));
        $size = (int) ($options['size'] ?? 160);
        $strokeWidth = 14;
        $radius = 60;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference - ($pct / 100) * $circumference;

        return <<<HTML
        <div class="flex flex-col items-center justify-center p-6 rounded-2xl cms-surface-2 border cms-border font-mono text-center">
            <div class="relative w-[{$size}px] h-[{$size}px] flex items-center justify-center">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 160 160">
                    <!-- Background Circle -->
                    <circle cx="80" cy="80" r="{$radius}" style="stroke: var(--cms-border)" stroke-width="{$strokeWidth}" fill="transparent" />
                    <!-- Animated Progress Ring -->
                    <circle cx="80" cy="80" r="{$radius}"
                            style="stroke: {$color}"
                            stroke-width="{$strokeWidth}"
                            stroke-dasharray="{$circumference}"
                            stroke-dashoffset="{$offset}"
                            stroke-linecap="round"
                            fill="transparent"
                            class="transition-all duration-1000 ease-out"
                            data-scroll-magic="stroke" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-black cms-text">{$pct}%</span>
                    <span class="text-[10px] cms-text-muted uppercase tracking-wider">Ratio</span>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-sm font-bold cms-text">{$lbl}</div>
                <div class="text-xs cms-text-muted mt-1">{$sub}</div>
            </div>
        </div>
        HTML;
    }

    /**
     * Renders an animated SVG line / growth trend chart.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @param  array<string, mixed>  $options
     */
    public function renderLine(array $points, array $options = []): string
    {
        if (empty($points)) {
            return '';
        }

        $title = ! empty($options['title']) ? htmlspecialchars((string) $options['title']) : '';
        $values = array_map(fn ($p) => (float) ($p['value'] ?? 0), $points);
        $minVal = min($values);
        $maxVal = max($values);
        $range = $maxVal - $minVal > 0 ? ($maxVal - $minVal) : 1;

        $width = 500;
        $height = 200;
        $padding = 30;
        $plotWidth = $width - ($padding * 2);
        $plotHeight = $height - ($padding * 2);

        $n = count($points);
        $stepX = $n > 1 ? $plotWidth / ($n - 1) : $plotWidth;

        $coords = [];
        $svgPoints = [];
        foreach ($points as $idx => $p) {
            $x = $padding + ($idx * $stepX);
            $norm = (((float) ($p['value'] ?? 0)) - $minVal) / $range;
            $y = $height - $padding - ($norm * $plotHeight);
            $coords[] = ['x' => $x, 'y' => $y, 'label' => htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'val' => $p['value'] ?? 0];
            $svgPoints[] = "{$x},{$y}";
        }

        $polyPoints = implode(' ', $svgPoints);
        $header = ! empty($title) ? "<h4 class=\"text-sm font-bold cms-text font-mono mb-4 flex items-center gap-2\"><span class=\"cms-accent\">&gt;</span> {$title}</h4>" : '';

        $dotsHtml = '';
        foreach ($coords as $c) {
            $dotsHtml .= "<circle cx=\"{$c['x']}\" cy=\"{$c['y']}\" r=\"4\" style=\"fill: var(--cms-accent)\" class=\"hover:r-6 transition-all\" />";
        }
        $firstLabel = $coords[0]['label'];
        $lastLabel = $coords[count($coords) - 1]['label'];

        return <<<HTML
        <div class="p-5 rounded-2xl cms-surface-2 border cms-border font-mono text-xs w-full overflow-hidden">
            {$header}
            <svg viewBox="0 0 {$width} {$height}" class="w-full h-48 overflow-visible">
                <polyline fill="none" style="stroke: var(--cms-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="{$polyPoints}" data-scroll-magic="draw" />
                {$dotsHtml}
            </svg>
            <div class="flex items-center justify-between text-[10px] text-gray-500 pt-2 border-t cms-border">
                <span>{$firstLabel}</span>
                <span>{$lastLabel}</span>
            </div>
        </div>
        HTML;
    }

    /**
     * Renders a high-impact metric KPI counter card.
     */
    public function renderMetric(string $value, string $label, ?string $description = null): string
    {
        $v = htmlspecialchars($value);
        $lbl = htmlspecialchars($label);
        $desc = ! empty($description) ? '<div class="text-xs cms-text-muted mt-1 font-mono">'.htmlspecialchars($description).'</div>' : '';

        return <<<HTML
        <div class="p-6 rounded-2xl cms-surface-2 border cms-border text-center hover:cms-border-accent transition-colors group">
            <div class="text-3xl sm:text-4xl font-black cms-accent font-mono tracking-tight group-hover:scale-105 transition-transform">
                {$v}
            </div>
            <div class="text-xs font-bold cms-text uppercase mt-2 font-mono tracking-wider">
                {$lbl}
            </div>
            {$desc}
        </div>
        HTML;
    }
}

<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeoExportServiceInterface;
use DbCms\Core\Services\System\FrameworkSupportService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('geo:generate-llms-txt {--output= : Absolute private export directory}', function (GeoExportServiceInterface $service): int {
    try {
        $output = $this->option('output');
        if ($output !== null && ! is_string($output)) {
            throw new InvalidArgumentException('The export directory must be a string.');
        }
        $paths = $service->export($output);
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
    $this->info('Private GEO snapshots exported: '.implode(', ', $paths).'. Live public documents remain dynamic.');

    return 0;
})->purpose('Exports private offline GEO snapshots without replacing dynamic public routes');

Artisan::command('system:check-framework-support {--output= : Optional absolute report file}', function (FrameworkSupportService $monitor): int {
    $this->info('Inspecting Laravel annual release support (no vulnerability audit)...');

    $status = $monitor->statusForVersion(app()->version(), new DateTimeImmutable('today'));
    $this->table(['Metric', 'Status'], [
        ['Current Laravel Version', $status['current_version']],
        ['Release Policy', 'Annual releases: 18 months bug fixes, 2 years security fixes'],
        ['Bug Fixes Until', $status['bug_fixes_until'] ?? 'Unknown'],
        ['Security Fixes Until', $status['security_fixes_until'] ?? 'Unknown'],
        ['Vulnerability Audit', 'Not run; execute composer audit --locked separately'],
        ['Support Window Active', $status['support_active'] ? 'Active' : 'Expired'],
        ['Recommendation', $status['recommendation']],
    ]);

    $output = $this->option('output');
    if ($output !== null) {
        if (! is_string($output) || ! str_starts_with($output, '/') || ! is_dir(dirname($output))) {
            $this->error('Output must name a file in an existing absolute directory.');

            return 1;
        }
        if (file_put_contents($output, json_encode($status, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n") === false) {
            $this->error('Could not write the support report.');

            return 1;
        }
    }

    return 0;
})->purpose('Reports the installed framework support window from the maintained local table');

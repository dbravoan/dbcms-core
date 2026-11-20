<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Services;

use Carbon\Carbon;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use DbCms\Core\Services\Cms\FrontViewResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class MaintenanceService implements MaintenanceModeInterface
{
    protected string $lockFilePath;

    public function __construct(
        protected CoreFeatureSettings $settingsService,
        private readonly FrontViewResolver $views,
    ) {
        $this->lockFilePath = storage_path('framework/cms-maintenance.json');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Checks whether maintenance mode is currently active.
     */
    public function isActive(): bool
    {
        // 1. Direct environment / runtime configuration override
        if ((bool) Config::get('cms.maintenance_mode', false)) {
            return true;
        }

        // 2. Lock file check (fastest IO check, useful for CLI toggling)
        if (File::exists($this->lockFilePath)) {
            $data = json_decode((string) File::get($this->lockFilePath), true);
            if (is_array($data) && isset($data['active'])) {
                return (bool) $data['active'];
            }

            return true;
        }

        // 3. Core settings persisted in database
        try {
            return (bool) $this->settingsService->get('maintenance', 'is_enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retrieve all resolved maintenance settings.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        try {
            $all = $this->settingsService->getAll('maintenance');
        } catch (\Throwable) {
            $all = [];
        }

        // Fast IO lock file override (e.g. from CLI or enable() without DB)
        if (File::exists($this->lockFilePath)) {
            $lockData = json_decode((string) File::get($this->lockFilePath), true);
            if (is_array($lockData)) {
                if (isset($lockData['active'])) {
                    $all['is_enabled'] = (bool) $lockData['active'];
                }
                if (! empty($lockData['end_datetime'])) {
                    $all['end_datetime'] = (string) $lockData['end_datetime'];
                }
                if (! empty($lockData['message'])) {
                    $all['message'] = (string) $lockData['message'];
                }
            }
        }

        if ((bool) Config::get('cms.maintenance_mode', false)) {
            $all['is_enabled'] = true;
        }

        return [
            'is_enabled' => (bool) ($all['is_enabled'] ?? false),
            'mode' => (string) ($all['mode'] ?? 'default'),
            'title' => trim((string) ($all['title'] ?? '')) ?: (string) config('cms.name', 'CMS'),
            'logo_text' => trim((string) ($all['logo_text'] ?? '')) ?: (string) config('cms.name', 'CMS'),
            'accent_color' => (string) ($all['accent_color'] ?? '#00FF99'),
            'bg_color' => (string) ($all['bg_color'] ?? '#1A1A1A'),
            'end_datetime' => ! empty($all['end_datetime']) ? (string) $all['end_datetime'] : null,
            'show_countdown' => (bool) ($all['show_countdown'] ?? true),
            'headline' => (string) ($all['headline'] ?? 'PLATAFORMA EN CONSTRUCCIÓN'),
            'message' => (string) ($all['message'] ?? 'Estamos desplegando nuevas funcionalidades y optimizando la infraestructura. Volveremos a estar en línea muy pronto.'),
            'show_download_svg' => (bool) ($all['show_download_svg'] ?? false),
            'show_admin_link' => (bool) ($all['show_admin_link'] ?? true),
            'custom_html' => (string) ($all['custom_html'] ?? ''),
            'retry_after_fallback' => (int) ($all['retry_after_fallback'] ?? 3600),
        ];
    }

    /**
     * Parse end datetime as a Carbon instance if valid.
     */
    public function getEndDateTime(): ?Carbon
    {
        $settings = $this->getSettings();
        if (! empty($settings['end_datetime'])) {
            try {
                return Carbon::parse((string) $settings['end_datetime']);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Calculate seconds for HTTP 503 Retry-After header.
     */
    public function getRetryAfterSeconds(): int
    {
        $endDate = $this->getEndDateTime();
        if ($endDate && $endDate->isFuture()) {
            return max(60, (int) now()->diffInSeconds($endDate));
        }

        $settings = $this->getSettings();

        return (int) ($settings['retry_after_fallback'] ?? 3600);
    }

    /**
     * Enable maintenance mode.
     */
    public function enable(?string $endDatetime = null, ?string $message = null): void
    {
        $updates = ['is_enabled' => true];
        if ($endDatetime !== null) {
            $updates['end_datetime'] = $endDatetime;
        }
        if ($message !== null) {
            $updates['message'] = $message;
        }

        try {
            $this->settingsService->setMany('maintenance', $updates);
        } catch (\Throwable) {
            // In case DB is not reachable
        }

        $payload = [
            'active' => true,
            'enabled_at' => now()->toIso8601String(),
            'end_datetime' => $endDatetime,
            'message' => $message,
        ];

        File::put($this->lockFilePath, (string) json_encode($payload, JSON_PRETTY_PRINT));
    }

    /**
     * Disable maintenance mode.
     */
    public function disable(): void
    {
        try {
            $this->settingsService->set('maintenance', 'is_enabled', false);
        } catch (\Throwable) {
            // In case DB is not reachable
        }

        if (File::exists($this->lockFilePath)) {
            File::delete($this->lockFilePath);
        }
    }

    /**
     * Toggle maintenance mode state.
     */
    public function toggle(): bool
    {
        if ($this->isActive()) {
            $this->disable();

            return false;
        }

        $this->enable();

        return true;
    }

    public function renderHtml(): string
    {
        $settings = $this->getSettings();
        $retryAfter = $this->getRetryAfterSeconds();
        $endDateTime = $this->getEndDateTime();

        $viewName = $this->views->resolve('maintenance.index', 'maintenance::index');

        return View::make($viewName, [
            'settings' => $settings,
            'endDateTime' => $endDateTime,
            'retryAfter' => $retryAfter,
            'title' => $settings['title'],
            'logoText' => $settings['logo_text'],
            'accentColor' => $settings['accent_color'],
            'bgColor' => $settings['bg_color'],
            'showCountdown' => $settings['show_countdown'],
            'headline' => $settings['headline'],
            'message' => $settings['message'],
            'showDownloadSvg' => $settings['show_download_svg'],
            'showAdminLink' => $settings['show_admin_link'],
            'mode' => $settings['mode'],
            'customHtml' => $settings['custom_html'],
        ])->render();

    }
}

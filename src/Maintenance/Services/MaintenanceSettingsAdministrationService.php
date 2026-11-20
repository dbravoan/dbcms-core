<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Services;

use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class MaintenanceSettingsAdministrationService implements MaintenanceSettingsAdministrationInterface
{
    public function __construct(private readonly CoreFeatureSettings $settings, private readonly MaintenanceService $maintenance) {}

    /** @param array<string, mixed> $settings */
    public function update(array $settings): void
    {
        $validated = Validator::make($settings, ['is_enabled' => 'nullable|boolean', 'mode' => 'required|in:default,custom_html', 'title' => 'nullable|string|max:150', 'logo_text' => 'nullable|string|max:50', 'accent_color' => 'nullable|string|max:25', 'bg_color' => 'nullable|string|max:25', 'end_datetime' => 'nullable|string|max:50', 'show_countdown' => 'nullable|boolean', 'headline' => 'nullable|string|max:255', 'message' => 'nullable|string', 'show_download_svg' => 'nullable|boolean', 'show_admin_link' => 'nullable|boolean', 'custom_html' => 'nullable|string', 'retry_after_fallback' => 'nullable|numeric|min:10'])->validate();
        foreach (['is_enabled', 'show_countdown', 'show_download_svg', 'show_admin_link'] as $key) {
            $validated[$key] = filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        DB::transaction(function () use ($validated): void {
            $this->settings->setMany('maintenance', $validated);
            DB::afterCommit(function () use ($validated): void {
                if ($validated['is_enabled']) {
                    $this->maintenance->enable($validated['end_datetime'] ?? null, $validated['message'] ?? null);
                } else {
                    $this->maintenance->disable();
                }
            });
        });
    }

    public function view(): array
    {
        return ['settings' => $this->maintenance->getSettings(), 'isActive' => $this->maintenance->isActive(), 'endDateTime' => $this->maintenance->getEndDateTime(), 'retryAfter' => $this->maintenance->getRetryAfterSeconds()];
    }

    public function toggle(): bool
    {
        return $this->maintenance->toggle();
    }

    public function preview(): string
    {
        return $this->maintenance->renderHtml();
    }
}

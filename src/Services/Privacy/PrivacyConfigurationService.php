<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Privacy;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Events\PrivacyConfigurationValidating;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrivacyConfigurationService
{
    public function __construct(
        private readonly LegalPageService $legal,
        private readonly GeneralSettingsServiceInterface $settings,
        private readonly InstallationEnvironmentServiceInterface $environment,
    ) {}

    public function revision(): string
    {
        return hash('sha256', json_encode($this->legal->company(), JSON_THROW_ON_ERROR));
    }

    /** @param array<array-key, mixed> $adoptions */
    public function configure(LegalCompanyDTO $company, ?string $expectedRevision = null, array $adoptions = []): void
    {
        event(new PrivacyConfigurationValidating($company));
        try {
            DB::transaction(function () use ($company, $expectedRevision, $adoptions): void {
                DB::table('settings')->where('key', 'legal.processing')->lockForUpdate()->first();
                $this->settings->flushCache();
                if ($expectedRevision !== null && ! hash_equals($this->revision(), $expectedRevision)) {
                    throw ValidationException::withMessages(['revision' => 'La configuración de privacidad ha cambiado. Recarga la página y revisa los datos antes de guardar.']);
                }
                $this->legal->provision($company, $adoptions);
            });
        } finally {
            $this->settings->flushCache();
        }

        $days = (int) $this->legal->requireProcessing('core')['retention_days'];
        $this->environment->write(['LOG_CHANNEL' => 'daily', 'LOG_DAILY_DAYS' => (string) $days]);
        if (app()->configurationIsCached() && Artisan::call('config:clear') !== 0) {
            throw new \RuntimeException('Legal information was saved, but the configuration cache could not be cleared. Retry before reopening traffic.');
        }
        config(['logging.default' => 'daily', 'logging.channels.daily.days' => $days]);
    }
}

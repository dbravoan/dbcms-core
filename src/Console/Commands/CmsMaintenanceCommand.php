<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use Illuminate\Console\Command;

class CmsMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:maintenance 
                            {action=status : Acción a ejecutar: status, enable, disable, toggle}
                            {--until= : Fecha y hora estimada de reapertura (formato ISO o YYYY-MM-DD HH:MM:SS)}
                            {--message= : Mensaje explicativo para los visitantes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestiona el estado del Modo Mantenimiento y Construcción de dbCMS';

    public function handle(MaintenanceModeInterface $service): int
    {
        $action = (string) $this->argument('action');
        $until = $this->option('until') ? (string) $this->option('until') : null;
        $message = $this->option('message') ? (string) $this->option('message') : null;
        if ($action !== 'status' && ! $service->isAvailable()) {
            $this->error('El módulo de mantenimiento no está disponible.');

            return self::FAILURE;
        }

        switch ($action) {
            case 'enable':
                $service->enable($until, $message);
                $this->info('[OK] Modo Mantenimiento ACTIVADO exitosamente.');
                $this->line(' - HTTP Status: <fg=yellow>503 Service Unavailable</>');
                if ($until) {
                    $this->line(" - Fecha Límite: <fg=cyan>{$until}</>");
                }
                $this->line(" - Retry-After: <fg=green>{$service->getRetryAfterSeconds()}s</>");

                return self::SUCCESS;

            case 'disable':
                $service->disable();
                $this->info('[OK] Modo Mantenimiento DESACTIVADO. Portal público 100% operativo.');

                return self::SUCCESS;

            case 'toggle':
                $active = $service->toggle();
                if ($active) {
                    $this->warn('[OK] Modo Mantenimiento ACTIVADO.');
                } else {
                    $this->info('[OK] Modo Mantenimiento DESACTIVADO.');
                }

                return self::SUCCESS;

            case 'status':
            default:
                $isActive = $service->isActive();
                $settings = $service->getSettings();
                $endDate = $service->getEndDateTime();

                $this->line('');
                $this->line(' <fg=green;options=bold>>_dbCMS Estado de Mantenimiento</>');
                $this->line(' ----------------------------------------------------');
                $this->line(' Estado:       '.($isActive ? '<fg=red;options=bold>ACTIVO (503)</>' : '<fg=green;options=bold>INACTIVO (200 Operativo)</>'));
                $this->line(' Modo:         <fg=cyan>'.($settings['mode'] ?? 'default').'</>');
                $this->line(' Titular:      '.($settings['title'] ?? 'David Bravo'));
                $this->line(' Logo:         <fg=green>'.($settings['logo_text'] ?? '>_db').'</>');
                $this->line(' Fecha Fin:    '.($endDate ? $endDate->toDateTimeString() : '<fg=gray>No programada</>'));
                $this->line(' Retry-After:  <fg=yellow>'.$service->getRetryAfterSeconds().'s</>');
                $this->line(' Bypass Staff: <fg=green>Habilitado</>');
                $this->line('');

                return self::SUCCESS;
        }
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Install\ApplicationScaffolder;
use Illuminate\Console\Command;

final class SetupCmsCommand extends Command
{
    protected $signature = 'cms:setup
        {--theme=default-clean : Identificador del tema público instalado}
        {--admin-theme=dbcms : Identificador de la apariencia administrativa}
        {--modules= : Identificadores de módulos separados por comas}
        {--blocks= : Identificadores de paquetes de bloques separados por comas}';

    protected $description = 'Prepara la integración de dbCMS en una aplicación Laravel nueva, sin migrar datos';

    public function handle(ApplicationScaffolder $scaffolder): int
    {
        try {
            $scaffolder->prepare(
                (string) $this->option('theme'),
                (string) $this->option('admin-theme'),
                (string) $this->option('modules'),
                (string) $this->option('blocks'),
            );
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Integración preparada. Ejecuta npm install && npm run build y abre /install con el código CMS_INSTALL_TOKEN del archivo .env.');

        return self::SUCCESS;
    }
}

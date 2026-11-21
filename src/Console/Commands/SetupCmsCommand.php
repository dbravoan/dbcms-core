<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Install\ApplicationScaffolder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class SetupCmsCommand extends Command
{
    protected $signature = 'cms:setup
        {--theme=default-clean : Identificador del tema público instalado}
        {--admin-theme=dbcms : Identificador de la apariencia administrativa}
        {--modules= : Identificadores de módulos separados por comas}
        {--blocks= : Identificadores de paquetes de bloques separados por comas}
        {--resolve-packages= : Devuelve en JSON la composición a partir de nombres Composer instalados}';

    protected $description = 'Prepara la integración de dbCMS en una aplicación Laravel nueva, sin migrar datos';

    public function handle(ApplicationScaffolder $scaffolder): int
    {
        $selection = $this->option('resolve-packages');
        if ($selection !== null || $this->input->hasParameterOption('--resolve-packages')) {
            try {
                $result = $scaffolder->resolvePackages($this->packageSelection((string) $selection));
            } catch (\DomainException|\InvalidArgumentException|\JsonException $exception) {
                $result = ['error' => $exception->getMessage()];
            }
            $this->output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

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

    /** @return array<string, list<string>> */
    private function packageSelection(string $json): array
    {
        $selection = json_decode($json, flags: JSON_THROW_ON_ERROR);
        if (! $selection instanceof \stdClass) {
            throw new \InvalidArgumentException('La selección de paquetes debe ser un objeto JSON.');
        }
        $packages = [];
        foreach ((array) $selection as $flag => $names) {
            if (! in_array($flag, ['theme', 'admin-theme', 'module', 'block-package', 'package'], true) || ! is_array($names)) {
                throw new \InvalidArgumentException('La selección debe contener listas de nombres Composer completos por opción.');
            }
            $packages[$flag] = [];
            foreach ($names as $name) {
                if (! is_string($name) || preg_match('~\A[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*\z~D', $name) !== 1) {
                    throw new \InvalidArgumentException("La opción --{$flag} requiere nombres Composer completos.");
                }
                $packages[$flag][] = $name;
            }
        }

        return $packages;
    }
}

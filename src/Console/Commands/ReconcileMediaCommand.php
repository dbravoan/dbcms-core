<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Console\Command;

final class ReconcileMediaCommand extends Command
{
    protected $signature = 'cms:media:reconcile';

    protected $description = 'Incorpora a la biblioteca los archivos locales que aún no tienen un registro';

    public function handle(MediaManagerService $media): int
    {
        $count = $media->registerLocalFiles();
        $this->info("Archivos registrados: {$count}.");

        return self::SUCCESS;
    }
}

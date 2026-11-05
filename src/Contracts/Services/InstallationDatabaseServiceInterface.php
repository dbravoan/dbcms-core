<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Install\DatabaseSetupDTO;
use DbCms\Core\DTOs\Install\InstallationResultDTO;

interface InstallationDatabaseServiceInterface
{
    public function test(DatabaseSetupDTO $database): InstallationResultDTO;

    public function save(DatabaseSetupDTO $database): void;
}

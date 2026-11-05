<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Install\InstallationAdminDTO;

interface InstallationAdminServiceInterface
{
    public function finalize(InstallationAdminDTO $admin, bool $force = false): void;
}

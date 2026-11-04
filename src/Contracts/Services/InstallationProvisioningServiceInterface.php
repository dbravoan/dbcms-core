<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Install\InstallationResultDTO;
use Symfony\Component\Console\Output\OutputInterface;

interface InstallationProvisioningServiceInterface
{
    public function provision(bool $withDemo = false, ?OutputInterface $output = null): InstallationResultDTO;
}

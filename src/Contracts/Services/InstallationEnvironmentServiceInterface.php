<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use Symfony\Component\Console\Output\OutputInterface;

interface InstallationEnvironmentServiceInterface
{
    public function ensure(?OutputInterface $output = null): bool;

    /** @param array<string, string> $values */
    public function write(array $values): void;
}

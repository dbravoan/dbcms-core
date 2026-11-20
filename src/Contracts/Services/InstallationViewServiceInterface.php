<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface InstallationViewServiceInterface
{
    /** @return array<string, mixed> */
    public function requirements(): array;

    /** @return array<string, mixed> */
    public function database(): array;

    /** @return array<string, mixed> */
    public function administrator(): array;

    /** @return array<string, mixed> */
    public function complete(): array;
}

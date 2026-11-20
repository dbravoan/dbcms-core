<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\User;

interface AdminDashboardServiceInterface
{
    /** @return array<string, mixed> */
    public function data(User $user): array;
}

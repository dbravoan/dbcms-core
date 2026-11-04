<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use Illuminate\Support\Collection;

interface NotificationRecipientResolverInterface
{
    /** @return Collection<int, User> */
    public function recipients(SystemNotification $notification): Collection;

    public function allows(SystemNotification $notification, User $user): bool;

    public function setPreference(User $user, string $module, bool $enabled): void;

    public function emailEnabled(User $user, string $module = '*'): bool;
}

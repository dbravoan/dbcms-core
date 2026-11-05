<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Auth;

use DbCms\Core\Models\User;

final readonly class OAuthIdentity
{
    public function __construct(public User $user, public bool $isService) {}
}

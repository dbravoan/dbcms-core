<?php

declare(strict_types=1);

namespace DbCms\Core\Observers;

use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\TokenRevocationService;
use DbCms\Core\Services\Privacy\AccountPrivacyService;

final class UserSecurityObserver
{
    public function __construct(private readonly TokenRevocationService $tokens, private readonly AccountPrivacyService $privacy) {}

    public function deleting(User $user): void
    {
        $this->privacy->assertDeletionAllowed($user);
        $this->tokens->forUser($user);
        $this->privacy->eraseOwnedCopies($user);
    }
}

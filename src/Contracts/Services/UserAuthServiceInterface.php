<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Auth\LoginUserDTO;
use DbCms\Core\DTOs\Auth\RegisterUserDTO;
use DbCms\Core\Models\User;

interface UserAuthServiceInterface
{
    /**
     * Registers a new user and dispatches welcome events.
     */
    public function registerUser(RegisterUserDTO $dto): User;

    /**
     * Authenticates credentials and returns a personal access OAuth2 token.
     *
     * @return array{user: User, token: string, expires_at: string}
     */
    public function authenticate(LoginUserDTO $dto): array;

    /**
     * Revokes active tokens for the specified user.
     */
    public function revokeTokens(User $user): bool;
}

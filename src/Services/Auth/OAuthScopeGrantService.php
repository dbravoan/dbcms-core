<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Auth;

use DbCms\Core\Enums\OAuthScope;
use DbCms\Core\Models\User;
use Illuminate\Validation\ValidationException;

final class OAuthScopeGrantService
{
    public function allows(User $user, string $scope): bool
    {
        if (! array_key_exists($scope, OAuthScope::definitions()) && $scope !== '*') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return match ($scope) {
            OAuthScope::READ_CONTENT->value => true,
            OAuthScope::MCP_ACCESS->value, OAuthScope::MCP_READ->value => $user->hasPermission('access:admin'),
            OAuthScope::MCP_WRITE->value, OAuthScope::MCP_DESTRUCTIVE->value, OAuthScope::WRITE_CONTENT->value => $this->canManageContent($user),
            OAuthScope::M2M_SERVICE->value => (bool) $user->getAttribute('is_service_account') && $user->hasPermission('access:admin'),
            OAuthScope::MANAGE_USERS->value => $user->hasPermission('manage:users'),
            OAuthScope::AI_USE->value => $user->hasPermission('use:ai'),
            OAuthScope::AI_MANAGE->value => $user->hasPermission('manage:ai'),
            OAuthScope::CREATIVE_READ->value, OAuthScope::CREATIVE_WRITE->value => $user->hasPermission('manage:creative-studio'),
            OAuthScope::SOCIAL_READ->value => $user->hasPermission('read:social-publishing') || $user->hasPermission('manage:social-publishing'),
            OAuthScope::SOCIAL_WRITE->value => $user->hasPermission('manage:social-publishing') || $user->hasPermission('approve:social-publishing'),
            OAuthScope::SOCIAL_ACCOUNTS->value => $user->hasPermission('manage:social-accounts'),
            OAuthScope::SHOP_READ->value => true,
            OAuthScope::SHOP_MANAGE->value, OAuthScope::SHOP_ORDERS->value => $user->hasPermission('manage:shop'),
            default => false,
        };
    }

    private function canManageContent(User $user): bool
    {
        foreach (['manage:content', 'manage:posts', 'manage:pages', 'manage:shop', 'manage:forms', 'manage:media'] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $scopes */
    public function validate(User $user, array $scopes): void
    {
        foreach ($scopes as $scope) {
            if (! $this->allows($user, $scope)) {
                throw ValidationException::withMessages(['scopes' => 'A requested scope is not available to this account.']);
            }
        }
    }
}

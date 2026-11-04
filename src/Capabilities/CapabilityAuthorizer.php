<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

use DbCms\Core\Enums\OAuthScope;
use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\User;
use Laravel\Passport\Passport;

class CapabilityAuthorizer
{
    public function user(ActorContext $actor): ?User
    {
        return $actor->userId === null ? null : User::find($actor->userId);
    }

    public function allowed(ActorContext $actor, string $ability, ?string $permission): bool
    {
        $user = $this->user($actor);
        if ($user === null || ! in_array($actor->origin, ['web', 'api', 'mcp', 'job'], true)) {
            return false;
        }
        if ($actor->origin === 'web') {
            return ! $actor->isService && ! (bool) $user->getAttribute('is_service_account')
                && $this->allowsUser($user, [], $ability, $permission, 'web');
        }
        if ($actor->tokenId === null || $actor->clientId === null) {
            return false;
        }
        $token = Passport::token()->find($actor->tokenId);
        $client = Passport::client()->find($actor->clientId);
        if ($token === null || $client === null || (bool) $token->getAttribute('revoked') || (bool) $client->getAttribute('revoked')
            || (string) $token->getAttribute('client_id') !== $actor->clientId
            || ($token->getAttribute('expires_at') !== null && now()->greaterThan($token->getAttribute('expires_at')))) {
            return false;
        }
        $scopes = array_values(array_intersect($actor->scopes, (array) $token->getAttribute('scopes')));
        if ($actor->isService) {
            $binding = OAuthServiceAccount::find($actor->clientId);
            if (! (bool) $user->getAttribute('is_service_account') || $token->getAttribute('user_id') !== null
                || ! in_array('client_credentials', (array) $client->getAttribute('grant_types'), true)
                || $binding === null || (int) $binding->getAttribute('user_id') !== $actor->userId
                || array_diff((array) $token->getAttribute('scopes'), $binding->allowed_scopes) !== []) {
                return false;
            }
        } elseif ((bool) $user->getAttribute('is_service_account') || (string) $token->getAttribute('user_id') !== (string) $actor->userId
            || ! in_array($client->getAttribute('provider'), [null, 'users'], true)) {
            return false;
        }

        return $this->allowsUser($user, $scopes, $ability, $permission, $actor->origin);
    }

    /** @param list<string> $scopes */
    public function allowsUser(?User $user, array $scopes, string $ability, ?string $permission, string $origin): bool
    {
        if (! in_array($ability, ['read', 'write', 'destructive'], true) || $user === null
            || ($permission !== null && ! $user->isAdmin() && ! $user->hasPermission($permission))) {
            return false;
        }
        if ($origin === 'web') {
            return true;
        }
        $required = $this->scopeFor($ability, $origin, $permission);

        return in_array('*', $scopes, true) || in_array($required, $scopes, true);
    }

    public function scopeFor(string $ability, string $origin = 'mcp', ?string $permission = null): string
    {
        if ($origin === 'api') {
            if ($permission === 'use:ai') {
                return OAuthScope::AI_USE->value;
            }
            if ($permission === 'manage:ai') {
                return OAuthScope::AI_MANAGE->value;
            }
            if ($permission === 'manage:creative-studio') {
                return ($ability === 'read' ? OAuthScope::CREATIVE_READ : OAuthScope::CREATIVE_WRITE)->value;
            }
            if ($permission === 'manage:social-accounts') {
                return OAuthScope::SOCIAL_ACCOUNTS->value;
            }
            if (in_array($permission, ['read:social-publishing', 'manage:social-publishing', 'approve:social-publishing'], true)) {
                return ($ability === 'read' ? OAuthScope::SOCIAL_READ : OAuthScope::SOCIAL_WRITE)->value;
            }

            return match ($ability) {
                'read' => OAuthScope::READ_CONTENT->value, 'destructive' => OAuthScope::ADMIN_SYSTEM->value, default => OAuthScope::WRITE_CONTENT->value
            };
        }

        return match ($ability) {
            'read' => OAuthScope::MCP_READ->value, 'destructive' => OAuthScope::MCP_DESTRUCTIVE->value, default => OAuthScope::MCP_WRITE->value
        };
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Auth;

use DbCms\Core\DTOs\Auth\OAuthIdentity;
use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

final class OAuthIdentityResolver
{
    /** @param list<string> $scopes */
    public function resolve(Token $token, Client $client, mixed $jwtSubject, string $jwtClientId, array $scopes): OAuthIdentity
    {
        if ((bool) $client->getAttribute('revoked') || (string) $token->getAttribute('client_id') !== $jwtClientId || (string) $client->getKey() !== $jwtClientId
            || ! in_array($client->getAttribute('provider'), [null, 'users'], true)) {
            throw new AuthenticationException;
        }
        $userId = $token->getAttribute('user_id');
        if ($userId !== null) {
            // Human IDs come from the persisted token, never a client UUID/ULID.
            if (! preg_match('/^[1-9][0-9]*$/D', (string) $userId) || (string) $jwtSubject !== (string) $userId) {
                throw new AuthenticationException;
            }
            $user = User::find($userId);
            if (! $user instanceof User || (bool) $user->getAttribute('is_service_account')) {
                throw new AuthenticationException;
            }

            return new OAuthIdentity($user, false);
        }

        if (! in_array('client_credentials', (array) $client->getAttribute('grant_types'), true)) {
            throw new AuthenticationException;
        }
        $binding = OAuthServiceAccount::find($jwtClientId);
        $user = $binding?->user;
        if (! $user instanceof User || ! (bool) $user->getAttribute('is_service_account') || array_diff($scopes, $binding->allowed_scopes) !== []) {
            throw new AuthenticationException;
        }

        return new OAuthIdentity($user, true);
    }
}

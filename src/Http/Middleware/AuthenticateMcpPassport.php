<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\OAuthIdentityResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpPassport
{
    public function __construct(
        protected ResourceServer $server,
        protected OAuthIdentityResolver $identities
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        // Bearer credentials never travel in URLs (logs, caches and referrers).

        $psr = (new PsrHttpFactory(
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized: Invalid or expired Passport OAuth2 token.',
                ],
                'id' => null,
            ], 401);
        }

        $tokenId = (string) $psr->getAttribute('oauth_access_token_id');
        /** @var Token|null $token */
        $token = Passport::token()->find($tokenId);

        if ($token === null || (bool) $token->getAttribute('revoked')
            || ($token->getAttribute('expires_at') !== null && now()->greaterThanOrEqualTo($token->getAttribute('expires_at')))) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized: OAuth2 token has been revoked.',
                ],
                'id' => null,
            ], 401);
        }

        $userId = $psr->getAttribute('oauth_user_id');
        $clientId = (string) $psr->getAttribute('oauth_client_id');
        $tokenScopes = (array) ($token->getAttribute('scopes') ?? []);
        $jwtScopes = (array) $psr->getAttribute('oauth_scopes', []);
        $tokenScopes = array_values(array_intersect($tokenScopes, $jwtScopes));

        // Validate scope requirement
        if ($requiredScope !== null && ! in_array('*', $tokenScopes, true) && ! in_array($requiredScope, $tokenScopes, true)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32003,
                    'message' => "Forbidden: Token lacks required OAuth scope [{$requiredScope}].",
                ],
                'id' => null,
            ], 403);
        }

        try {
            $client = $token->client;
            if (! $client instanceof Client) {
                throw new AuthenticationException;
            }
            $identity = $this->identities->resolve($token, $client, $userId, $clientId, $tokenScopes);
        } catch (AuthenticationException) {
            return new JsonResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32001, 'message' => 'Unauthorized: The token has no active CMS identity.'], 'id' => null], 401);
        }

        $accessToken = AccessToken::fromPsrRequest($psr->withAttribute('oauth_scopes', $tokenScopes));
        $user = $identity->user->withAccessToken($accessToken);
        $request->setUserResolver(fn (): User => $user);
        Auth::setUser($user);
        $request->attributes->set('oauth_user_id', $user->getKey());
        $request->attributes->set('is_m2m', $identity->isService);

        $request->attributes->set('oauth_token_id', $tokenId);
        $request->attributes->set('oauth_client_id', $clientId);
        $request->attributes->set('oauth_scopes', $tokenScopes);

        return $next($request);
    }
}

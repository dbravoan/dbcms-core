<?php

declare(strict_types=1);

use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Tests\Support\PassportTestKeys;

/** @param list<string> $scopes */
function mcpTestBindIdentity(?User $user = null, array $scopes = ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive']): User
{
    PassportTestKeys::configure();
    $user ??= User::where('email', 'admin@db-cms.io')->firstOrFail();
    app(ClientRepository::class)->createPersonalAccessGrantClient('MCP regression fixture');
    $result = $user->createToken('MCP regression fixture', $scopes);
    $token = $result->getToken();
    $user->withAccessToken(new AccessToken(['oauth_access_token_id' => (string) $token->getKey(), 'oauth_client_id' => (string) $token->client_id, 'oauth_user_id' => (string) $user->getKey(), 'oauth_scopes' => $scopes]));
    Auth::setUser($user);
    request()->setUserResolver(fn () => $user);
    request()->attributes->set('oauth_scopes', $scopes);
    request()->attributes->set('oauth_token_id', (string) $token->getKey());
    request()->attributes->set('oauth_client_id', (string) $token->client_id);
    request()->attributes->set('is_m2m', false);

    return $user;
}

/**
 * Happy-path fixture: a human reviews the persisted plan through the real web
 * approval route. A confirm argument in the MCP request never approves it.
 *
 * @param  array<string,mixed>  $payload
 * @return array<string,mixed>
 */
function mcpTestHumanApproval(array $payload, User $human): array
{
    $payload = isset($payload['execution']) && is_array($payload['execution']) ? $payload['execution'] : $payload;
    if (($payload['status'] ?? null) !== 'approval_required') {
        return $payload;
    }
    $execution = Execution::findOrFail($payload['id']);
    $step = $execution->steps()->firstOrFail();
    expect($step->status)->toBe('approval_required');
    test()->withHeaders(['Authorization' => ''])->actingAs($human, 'web')->postJson(route('admin.executions.approve', $execution->getKey()), [
        'step' => $step->key, 'binding_hash' => $step->binding_hash,
    ])->assertSuccessful();
    $step->refresh();
    expect($step->status)->toBe('completed');

    return (array) $step->result;
}

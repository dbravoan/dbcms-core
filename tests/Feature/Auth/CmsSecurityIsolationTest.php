<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Http\Middleware\AuthenticateMcpPassport;
use DbCms\Core\Models\CapabilityAuditLog;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\ServiceAccountProvisioningService;
use DbCms\Core\Services\Capabilities\ActorContextFactory;
use DbCms\Core\Services\Capabilities\PlatformResourceCatalog;
use DbCms\Core\Services\Capabilities\ResourceCrudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Support\PassportTestKeys;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    PassportTestKeys::configure();
    app(ClientRepository::class)->createPersonalAccessGrantClient('Security isolation personal client');
    $this->authorRole = Role::firstOrCreate(['slug' => 'author'], ['name' => 'Author']);
    foreach (['access:admin', 'manage:posts', 'manage:media', 'use:ai'] as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $this->authorRole->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
});

test('a personal token with a missing user is rejected rather than becoming a machine token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('orphan', ['mcp:access'])->accessToken;
    User::withoutEvents(fn () => $user->delete());
    $request = Request::create('/mcp/cms', 'POST');
    $request->headers->set('Authorization', 'Bearer '.$token);
    $response = app(AuthenticateMcpPassport::class)->handle($request, fn () => response('unexpected'), 'mcp:access');

    expect($response->getStatusCode())->toBe(401);
    expect(User::where('email', 'mcp-agent@db-cms.io')->exists())->toBeFalse();
});

test('client credentials resolve the explicit service account instead of a numeric user', function (): void {
    $human = User::factory()->create();
    $client = app(ServiceAccountProvisioningService::class)->create('Bound machine', ['mcp:access', 'mcp:read'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $binding = OAuthServiceAccount::findOrFail($client->getKey());
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret, 'scope' => 'mcp:access mcp:read',
    ]);
    $response->assertOk();
    $request = Request::create('/mcp/cms', 'POST');
    $request->headers->set('Authorization', 'Bearer '.$response->json('access_token'));
    $result = app(AuthenticateMcpPassport::class)->handle($request, function (Request $request) use ($binding, $human) {
        expect($request->user()->getKey())->toBe($binding->user->getKey())->not->toBe($human->getKey());
        expect($request->attributes->get('is_m2m'))->toBeTrue();

        return response('bound');
    }, 'mcp:access');
    expect($result->getStatusCode())->toBe(200);
});

test('unbound machine clients are rejected', function (): void {
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Unbound');
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret, 'scope' => 'mcp:access',
    ]);
    $response->assertOk();
    $request = Request::create('/mcp/cms', 'POST');
    $request->headers->set('Authorization', 'Bearer '.$response->json('access_token'));
    expect(app(AuthenticateMcpPassport::class)->handle($request, fn () => response('unexpected'), 'mcp:access')->getStatusCode())->toBe(401);
});

test('machine tokens are denied when their scopes exceed the current explicit binding', function (): void {
    $client = app(ServiceAccountProvisioningService::class)->create('Scoped machine', ['mcp:access', 'mcp:read'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $response = $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret, 'scope' => 'mcp:access mcp:write']);
    $response->assertOk();
    $request = Request::create('/mcp/cms', 'POST');
    $request->headers->set('Authorization', 'Bearer '.$response->json('access_token'));
    expect(app(AuthenticateMcpPassport::class)->handle($request, fn () => response('unexpected'), 'mcp:access')->getStatusCode())->toBe(401);
});

test('capability policy revalidates client revocation scopes and service bindings', function (): void {
    $client = app(ServiceAccountProvisioningService::class)->create('Live machine', ['mcp:access', 'mcp:read'], (int) Role::where('slug', 'author')->firstOrFail()->getKey());
    $binding = OAuthServiceAccount::findOrFail($client->getKey());
    $this->post('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret, 'scope' => 'mcp:access mcp:read'])->assertOk();
    $token = Passport::token()->newQuery()->where('client_id', $client->getKey())->firstOrFail();
    $actor = new ActorContext((int) $binding->user_id, 'mcp', ['mcp:access', 'mcp:read'], (string) $client->getKey(), (string) $token->getKey(), true);
    $policy = app(CapabilityAuthorizer::class);
    expect($policy->allowed($actor, 'read', 'manage:posts'))->toBeTrue();
    $permissionId = Permission::where('slug', 'manage:posts')->value('id');
    $this->authorRole->permissions()->detach($permissionId);
    expect($policy->allowed($actor, 'read', 'manage:posts'))->toBeFalse();
    $this->authorRole->permissions()->attach($permissionId);
    $binding->update(['allowed_scopes' => ['mcp:access']]);
    expect($policy->allowed($actor, 'read', 'manage:posts'))->toBeFalse();
    $binding->update(['allowed_scopes' => ['mcp:access', 'mcp:read']]);
    $client->forceFill(['revoked' => true])->save();
    expect($policy->allowed($actor, 'read', 'manage:posts'))->toBeFalse();
});

test('deleting a user revokes and removes their access and refresh tokens', function (): void {
    $user = User::factory()->create();
    $result = $user->createToken('delete-me', ['read:content']);
    $token = $result->getToken();
    Passport::refreshToken()->forceFill(['id' => str_repeat('a', 80), 'access_token_id' => $token->getKey(), 'revoked' => false, 'expires_at' => now()->addDay()])->save();
    $user->delete();

    expect($token->fresh())->toBeNull();
    expect(Passport::refreshToken()->newQuery()->where('access_token_id', $token->getKey())->exists())->toBeFalse();
});

test('api login blocks privilege scope self-grant and service account passwords', function (): void {
    $user = User::factory()->create(['password' => 'valid-password']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'valid-password', 'scopes' => ['mcp:access', 'manage:users']])->assertUnprocessable();
    $user->forceFill(['is_service_account' => true])->save();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'valid-password'])->assertUnauthorized();
    $accounts = PrivacyFixtures::processingFor([])[0];
    $accounts['owner'] = 'accounts';
    $accounts['manual_review'] = true;
    unset($accounts['retention_days']);
    $accounts['retention'] = 'Until the account relationship ends, subject to reviewed restrictions';
    $legalSettings = app(GeneralSettingsServiceInterface::class);
    $legalSettings->set('legal.processing', [...$legalSettings->get('legal.processing', []), $accounts], 'legal', 'json');
    $this->postJson('/api/v1/auth/register', ['name' => 'Reserved', 'email' => 'mcp-agent@db-cms.io', 'password' => 'valid-password'])->assertUnprocessable()->assertJsonPath('error.code', 'RESERVED_SERVICE_IDENTITY');
});

test('api login limits a target account across changing IP addresses', function (): void {
    $email = 'rate-security@example.test';
    RateLimiter::clear('api-auth:login:account:'.hash('sha256', $email));
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'incorrect'])->assertUnauthorized();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'incorrect'])->assertStatus(429)->assertHeader('Retry-After');
});

test('MCP notification CRUD cannot read update or delete another user private notification', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = SystemNotification::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->getKey(), 'module' => 'test', 'type' => 'alert', 'title' => 'Own', 'message' => 'Own']);
    $private = SystemNotification::create(['uuid' => (string) Str::uuid(), 'user_id' => $other->getKey(), 'module' => 'test', 'type' => 'alert', 'title' => 'Private', 'message' => 'Private']);
    $registry = new CapabilityResourceRegistry;
    $registry->registerMany(PlatformResourceCatalog::all());
    $crud = new ResourceCrudService($registry);
    Auth::login($user);
    expect($crud->listing('notification')['total'])->toBe(1);
    expect(fn () => $crud->find('notification', $private->getKey()))->toThrow(ValidationException::class);
    expect(fn () => $crud->update('notification', $private->getKey(), ['is_read' => true]))->toThrow(ValidationException::class);
    expect(fn () => $crud->delete('notification', $private->getKey()))->toThrow(ValidationException::class);
    $crud->update('notification', $own->getKey(), ['is_read' => true]);
    expect($own->fresh()->read_at)->not->toBeNull();
});

it('requires an explicit service account role without creating any identity', function (): void {
    $clients = Client::count();
    $users = User::count();
    try {
        app(ServiceAccountProvisioningService::class)->create('Unassigned machine', ['mcp:access']);
        $this->fail('Implicit service roles must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('role_id');
    }
    expect(Client::count())->toBe($clients)
        ->and(User::count())->toBe($users)
        ->and(OAuthServiceAccount::count())->toBe(0);
});

test('requests awaiting human confirmation remain recorded in the capability audit', function (): void {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->getKey());
    mcpTestBindIdentity($admin);
    $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
    $menu = Menu::create(['name' => 'Approval audit', 'slug' => 'approval-audit', 'location' => 'header']);
    $result = app(CapabilityExecutor::class)->execute('cms_resource_delete', ['resource' => 'menu', 'id' => $menu->getKey()], $actor);
    expect($result['confirmation_required'] ?? false)->toBeTrue();
    expect(CapabilityAuditLog::where('tool', 'cms_resource_delete')->where('status', 'confirmation_required')->where('user_id', $admin->getKey())->exists())->toBeTrue();
    expect($menu->fresh())->not->toBeNull();
});

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Auth;

use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

final class ServiceAccountProvisioningService
{
    public function __construct(private readonly ClientRepository $clients, private readonly OAuthScopeGrantService $grants) {}

    /** @param list<string> $scopes */
    public function create(string $name, array $scopes, ?int $roleId = null, ?User $actor = null): Client
    {
        return DB::transaction(function () use ($name, $scopes, $roleId, $actor): Client {
            if (in_array('*', $scopes, true)) {
                throw ValidationException::withMessages(['scopes' => 'Service accounts require explicit scopes.']);
            }
            $role = $roleId === null ? null : Role::with('permissions')->find($roleId);
            if (! $role instanceof Role) {
                throw ValidationException::withMessages(['role_id' => 'Select an existing CMS role for the service account.']);
            }
            if ($actor !== null && ! $actor->isAdmin()) {
                if (in_array($role->slug, ['admin', 'super-admin'], true)) {
                    throw ValidationException::withMessages(['role_id' => 'Solo un administrador puede crear una cuenta de servicio administradora.']);
                }

                $granted = $role->permissions->pluck('slug')->all();
                $owned = $actor->roles()->with('permissions')->get()
                    ->flatMap(static fn (Role $role): array => $role->permissions->pluck('slug')->all())
                    ->unique()
                    ->all();
                if (array_diff($granted, $owned) !== []) {
                    throw ValidationException::withMessages(['role_id' => 'No puedes crear una cuenta de servicio con permisos que no posees.']);
                }
            }
            $client = $this->clients->createClientCredentialsGrantClient($name);
            $user = User::create([
                'name' => $name,
                'email' => 'service-'.$client->getKey().'@service.dbcms.invalid',
                'password' => Str::random(64),
            ]);
            $user->forceFill(['is_service_account' => true])->save();
            $user->roles()->attach($role->getKey());
            $this->grants->validate($user, $scopes);
            OAuthServiceAccount::create(['client_id' => $client->getKey(), 'user_id' => $user->getKey(), 'allowed_scopes' => array_values(array_unique($scopes))]);

            return $client;
        });
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Auth;

use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

final class TokenRevocationService
{
    public function forUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $clients = array_unique([...OAuthServiceAccount::where('user_id', $user->getKey())->pluck('client_id')->all(), ...Passport::client()->newQuery()->where('owner_type', $user->getMorphClass())->where('owner_id', $user->getKey())->pluck('id')->all()]);
            $tokens = Passport::token()->newQuery()->where(function ($query) use ($user, $clients): void {
                $query->where('user_id', $user->getKey())->orWhereIn('client_id', $clients);
            })->pluck('id')->all();
            Passport::client()->newQuery()->whereIn('id', $clients)->update(['revoked' => true]);
            $this->forTokens(array_values(array_map('strval', $tokens)));
        });
    }

    public function forClient(string $clientId): void
    {
        DB::transaction(function () use ($clientId): void {
            Passport::client()->newQuery()->whereKey($clientId)->update(['revoked' => true]);
            $tokens = Passport::token()->newQuery()->where('client_id', $clientId)->pluck('id')->all();
            $this->forTokens(array_values(array_map('strval', $tokens)));
        });
    }

    /** @param list<string> $ids */
    public function forTokens(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            Passport::token()->newQuery()->whereIn('id', $ids)->update(['revoked' => true]);
            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        });
    }
}

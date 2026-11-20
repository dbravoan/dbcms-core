<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ActorContextFactory
{
    public function fromRequest(Request $request, string $origin = 'web'): ActorContext
    {
        $user = $request->user() ?? Auth::user();

        return new ActorContext(
            userId: $user instanceof User ? (int) $user->getKey() : null,
            origin: $origin,
            scopes: array_values(array_map('strval', (array) $request->attributes->get('oauth_scopes', []))),
            clientId: $request->attributes->get('oauth_client_id'),
            tokenId: $request->attributes->get('oauth_token_id'),
            isService: (bool) $request->attributes->get('is_m2m', false),
            market: (string) $request->input('market', $request->route('market', 'es')),
            locale: (string) $request->input('locale', $request->route('locale', app()->getLocale())),
        );
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Tokens;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

final class TokensGetController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tokens = Token::with('client')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'tokens_page');

        $clients = Client::query()
            ->when(
                Schema::hasColumn('oauth_clients', 'grant_types'),
                fn ($q) => $q->whereJsonDoesntContain('grant_types', 'personal_access'),
                fn ($q) => $q->where('personal_access_client', false)->where('password_client', false)
            )
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'clients_page');

        // Map token user IDs to users
        $userIds = $tokens->pluck('user_id')->filter()->unique()->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return view('admin.tokens.index', [
            'tokens' => $tokens,
            'clients' => $clients,
            'users' => $users,
        ]);
    }
}

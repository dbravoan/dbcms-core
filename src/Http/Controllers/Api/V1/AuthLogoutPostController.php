<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Api\V1;

use DbCms\Core\Contracts\Services\UserAuthServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthLogoutPostController extends Controller
{
    public function __invoke(Request $request, UserAuthServiceInterface $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $service->revokeTokens($user);

        return response()->json(['message' => 'Tokens revoked successfully.']);
    }
}

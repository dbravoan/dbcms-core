<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Api\V1;

use DbCms\Core\Contracts\Services\UserAuthServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Api\LoginRequest;
use DbCms\Core\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class AuthLoginPostController extends Controller
{
    public function __invoke(LoginRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $authPayload = $service->authenticate($request->toDTO());

        return response()->json([
            'user' => new UserResource($authPayload['user']),
            'token' => $authPayload['token'],
            'expires_at' => $authPayload['expires_at'],
        ]);
    }
}

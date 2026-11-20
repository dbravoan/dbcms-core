<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Api\V1;

use DbCms\Core\Contracts\Services\UserAuthServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Api\RegisterRequest;
use DbCms\Core\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class AuthRegisterPostController extends Controller
{
    public function __invoke(RegisterRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $user = $service->registerUser($request->toDTO());

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}

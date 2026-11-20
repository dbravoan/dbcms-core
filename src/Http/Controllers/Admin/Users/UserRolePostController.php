<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Users;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use DbCms\Core\Models\User;
use Illuminate\Http\RedirectResponse;

final class UserRolePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, User $user): RedirectResponse
    {
        return $this->resources->update('user', $user, $request);
    }
}

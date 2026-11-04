<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Users;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\RedirectResponse;

final class UserDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(User $user): RedirectResponse
    {
        return $this->resources->delete('user', $user);
    }
}

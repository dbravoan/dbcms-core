<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Users;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class UsersGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = User::with('roles');

        $users = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['name', 'email'],
            ['role' => 'roles.slug'],
            'created_at',
            'desc'
        );

        $roles = Role::all();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }
}

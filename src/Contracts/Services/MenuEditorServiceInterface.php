<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\DTOs\Admin\MenuEditorData;

interface MenuEditorServiceInterface
{
    public function index(DatatableQueryData $input): MenuEditorData;
}

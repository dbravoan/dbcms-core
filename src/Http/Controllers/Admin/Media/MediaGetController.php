<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class MediaGetController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.media.index');
    }
}

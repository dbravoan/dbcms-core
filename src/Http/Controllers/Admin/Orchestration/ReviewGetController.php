<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Orchestration;

use DbCms\Core\Http\Adapters\Orchestration\ExecutionTransport;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ReviewGetController extends Controller
{
    public function __construct(private readonly ExecutionTransport $transport) {}

    public function __invoke(Request $request, string $execution): View
    {
        $this->transport->show($request, $execution);

        return view('admin.orchestration.review', ['executionId' => $execution]);
    }
}

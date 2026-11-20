<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Orchestration;

use DbCms\Core\Http\Adapters\Orchestration\ExecutionTransport;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowGetController extends Controller
{
    public function __construct(private readonly ExecutionTransport $transport) {}

    public function __invoke(Request $request, string $execution): JsonResponse
    {
        return response()->json($this->transport->show($request, $execution));
    }
}

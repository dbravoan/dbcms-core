<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Seo;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\RobotsTxtService;
use Illuminate\Http\Response;

final class RobotsGetController extends Controller
{
    public function __invoke(RobotsTxtService $service): Response
    {
        return response($service->generate(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

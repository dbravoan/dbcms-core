<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Jobs;

use DbCms\Core\Forms\Services\FormActionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverFormAction implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $actionId) {}

    public function handle(FormActionService $actions): void
    {
        $actions->deliver($this->actionId);
    }
}

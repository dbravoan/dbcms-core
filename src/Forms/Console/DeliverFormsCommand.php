<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Console;

use DbCms\Core\Forms\Jobs\DeliverFormAction;
use DbCms\Core\Forms\Models\FormSubmissionAction;
use Illuminate\Console\Command;

/** Recovers committed outbox work when queue dispatch failed or a worker died. */
final class DeliverFormsCommand extends Command
{
    protected $signature = 'forms:deliver';

    protected $description = 'Deliver pending form actions with bounded retries';

    public function handle(): int
    {
        FormSubmissionAction::query()->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<=', now()->subMinutes(5))->orderBy('id')
            ->each(static function (FormSubmissionAction $action): void {
                try {
                    DeliverFormAction::dispatch($action->id);
                } catch (\Throwable) {
                    // Durable status is recorded by FormActionService.
                }
            }, 100);

        return self::SUCCESS;
    }
}

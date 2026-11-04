<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Privacy\CorePrivacyService;
use Illuminate\Console\Command;

final class PrivacyPruneCommand extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Prune expired core sessions, tokens, security records, notifications and consent receipts';

    public function handle(CorePrivacyService $privacy): int
    {
        $needsReview = false;
        foreach ($privacy->prune() as $kind => $count) {
            if (str_ends_with($kind, '_requiring_review') && $count > 0) {
                $needsReview = true;
                $this->warn($kind.': '.$count.'. Resolve pending work before completing retention cleanup.');
            } else {
                $this->info($kind.': '.$count);
            }
        }

        return $needsReview ? self::FAILURE : self::SUCCESS;
    }
}

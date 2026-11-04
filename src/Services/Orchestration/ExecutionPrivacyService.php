<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Orchestration;

use Carbon\CarbonInterface;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Services\Privacy\LegalPageService;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Retention never reinterprets payloads or discards unfinished remote receipts. */
final class ExecutionPrivacyService
{
    /** @param list<string> $ids
     * @return list<array<string,mixed>>
     */
    public function export(array $ids): array
    {
        return array_values(Execution::query()->whereIn('id', $ids)->with('steps')->orderBy('id')->get()->map(static function (Execution $execution): array {
            $data = $execution->makeVisible(['actor', 'request_hash', 'idempotency_key'])->toArray();
            $data['approvals'] = DB::table('cms_execution_approvals')->whereIn('step_id', $execution->steps->modelKeys())->orderBy('id')->get()->map(static fn (object $approval): array => (array) $approval)->all();

            return $data;
        })->all());
    }

    /** @param list<string> $ids */
    public function erase(array $ids, bool $retentionReleased = false): int
    {
        $cutoff = $this->cutoff($retentionReleased);
        $ids = array_values(array_unique($ids));
        sort($ids);
        $locks = [];
        try {
            foreach ($ids as $id) {
                $lock = Cache::lock('cms-execution:'.$id, 1260);
                if (! $lock->get()) {
                    throw new DomainException('An execution is being processed; nothing was erased.');
                }
                $locks[] = $lock;
            }

            return DB::transaction(function () use ($ids, $cutoff, $retentionReleased): int {
                $executions = Execution::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                foreach ($executions as $execution) {
                    if (! in_array($execution->status, ['completed', 'cancelled'], true) || $execution->updated_at === null || $execution->updated_at->greaterThan($cutoff)) {
                        throw new DomainException('An execution is unfinished or within its retention period; nothing was erased.');
                    }
                    $steps = $execution->steps()->lockForUpdate()->get();
                    foreach ($steps as $step) {
                        if ($step->updated_at === null || $step->updated_at->greaterThan($cutoff)) {
                            throw new DomainException('Recent execution evidence is still within its retention period.');
                        }
                        if ($step->status !== 'completed' && ! ($execution->status === 'cancelled' && $step->status === 'failed' && empty($step->resolved_arguments['provider_request_id']) && empty($step->result['provider_request_id']))) {
                            throw new DomainException('An execution has unresolved steps or remote checkpoints; reconcile them before erasure.');
                        }
                    }
                    $approvals = DB::table('cms_execution_approvals')->whereIn('step_id', $steps->modelKeys());
                    if ((clone $approvals)->where('updated_at', '>', $cutoff)->exists() || (! $retentionReleased && $approvals->whereNull('consumed_at')->exists())) {
                        throw new DomainException('Recent or unconsumed approval evidence remains; document its retention release before erasure.');
                    }
                }

                return Execution::query()->whereIn('id', $executions->modelKeys())->delete();
            });
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    public function prune(): int
    {
        $cutoff = $this->cutoff(false);
        $count = 0;
        Execution::query()->whereIn('status', ['completed', 'cancelled'])->where('updated_at', '<=', $cutoff)->orderBy('id')->chunkById(100, function ($executions) use (&$count): void {
            foreach ($executions as $execution) {
                try {
                    $count += $this->erase([$execution->id]);
                } catch (DomainException) {
                    // In-flight locks, unresolved steps and approval evidence remain intact.
                }
            }
        });

        return $count;
    }

    private function cutoff(bool $retentionReleased): CarbonInterface
    {
        $policy = app(LegalPageService::class)->requireProcessing('executions', true);
        $days = (int) ($policy['retention_days'] ?? 0);
        if ($days < 1 && ! $retentionReleased) {
            throw new DomainException('Execution retention needs documented manual review; use explicit IDs and a retention release.');
        }

        return $days > 0 ? now()->subDays($days) : now()->subMinutes(30);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Console;

use DbCms\Core\Search\Models\SearchTerm;
use DbCms\Core\Search\Services\SearchSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SearchPrivacyCommand extends Command
{
    protected $signature = 'search:privacy {action : export, erase or prune} {--id=* : Explicit search term row IDs} {--verified : Identity, scope and retention restrictions have been checked}';

    protected $description = 'Manage search term retention and data-subject requests';

    public function handle(SearchSettings $settings): int
    {
        $action = $this->argument('action');
        if (! is_string($action)) {
            $this->error('The action must be export, erase or prune.');

            return self::FAILURE;
        }

        if ($action === 'prune') {
            $days = $settings->retentionDays();
            $cutoff = now()->subDays($days);
            $deleted = SearchTerm::query()
                ->where(static function ($query) use ($cutoff): void {
                    $query->where('last_seen_at', '<', $cutoff)
                        ->orWhere(static fn ($query) => $query->whereNull('last_seen_at')->where('updated_at', '<', $cutoff));
                })
                ->delete();

            $this->info("Removed {$deleted} search terms older than {$days} days.");

            return self::SUCCESS;
        }

        $ids = $this->option('id');
        if (! in_array($action, ['export', 'erase'], true) || ! $this->option('verified') || ! is_array($ids) || $ids === []) {
            $this->error('Use export or erase with explicit --id values and --verified after checking the request.');

            return self::FAILURE;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            $this->error('No valid IDs were provided.');

            return self::FAILURE;
        }

        $terms = SearchTerm::query()->whereIn('id', $ids)->get();
        if ($action === 'erase') {
            $deleted = SearchTerm::query()->whereIn('id', $ids)->delete();
            $this->info("Erased {$deleted} search terms.");

            return self::SUCCESS;
        }

        $path = 'privacy/search-terms-'.Str::uuid().'.json';
        Storage::disk('local')->put($path, json_encode($terms->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'private');
        $this->info('Private export: '.Storage::disk('local')->path($path).'. Delete it after fulfilling the request.');

        return self::SUCCESS;
    }
}

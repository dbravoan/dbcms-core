<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Orchestration\ExecutionPrivacyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ExecutionPrivacyCommand extends Command
{
    protected $signature = 'executions:privacy {action : export, erase or prune} {--id=* : Explicit execution UUIDs} {--verified : Identity, scope and third-party rights were checked} {--retention-released : A documented manual retention review permits release}';

    protected $description = 'Manage completed execution data without deleting live jobs or approval evidence';

    public function handle(ExecutionPrivacyService $privacy): int
    {
        try {
            $action = $this->argument('action');
            if ($action === 'prune') {
                $this->info('Removed '.$privacy->prune().' expired settled executions.');

                return self::SUCCESS;
            }
            $ids = $this->option('id');
            if (! in_array($action, ['export', 'erase'], true) || ! $this->option('verified') || ! is_array($ids) || $ids === [] || count($ids) > 1000) {
                $this->error('Use explicit --id UUIDs and --verified after checking identity, scope and third-party information.');

                return self::FAILURE;
            }
            $validated = [];
            foreach ($ids as $id) {
                if (! is_string($id) || ! Str::isUuid($id)) {
                    $this->error('Every --id must be a valid execution UUID.');

                    return self::FAILURE;
                }
                $validated[] = $id;
            }
            $ids = array_values(array_unique($validated));
            if ($action === 'erase') {
                $this->info('Removed '.$privacy->erase($ids, (bool) $this->option('retention-released')).' settled executions.');

                return self::SUCCESS;
            }
            $path = 'privacy/executions-'.Str::uuid().'.json';
            Storage::disk('local')->put($path, json_encode($privacy->export($ids), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'private');
            $this->info('Private export: '.Storage::disk('local')->path($path).'. Review third-party content before delivery and delete after fulfillment.');

            return self::SUCCESS;
        } catch (\DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}

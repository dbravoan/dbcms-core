<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Privacy\AccountPrivacyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AccountPrivacyCommand extends Command
{
    protected $signature = 'accounts:privacy {action : export or erase} {id : Explicit account ID} {--verified : Identity, scope and owner retention restrictions have been checked}';

    protected $description = 'Export or erase core account data; other owners handle their retained business data';

    public function handle(AccountPrivacyService $privacy): int
    {
        $id = filter_var($this->argument('id'), FILTER_VALIDATE_INT);
        $action = $this->argument('action');
        if (! $this->option('verified') || $id === false || $id < 1 || ! in_array($action, ['export', 'erase'], true)) {
            $this->error('Use an explicit account ID and --verified after checking identity, scope and retention restrictions.');

            return self::FAILURE;
        }
        if ($action === 'erase') {
            $privacy->erase($id);
            $this->info('Core account erased. Complete the request with each business data owner.');

            return self::SUCCESS;
        }
        $path = 'privacy/account-'.Str::uuid().'.json';
        Storage::disk('local')->put($path, json_encode($privacy->export($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'private');
        $this->info('Private core export: '.Storage::disk('local')->path($path).'. Delete after fulfilment; business owners export their own records.');

        return self::SUCCESS;
    }
}

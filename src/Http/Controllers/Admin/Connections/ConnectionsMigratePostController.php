<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Connections;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaMigrationService;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ConnectionsMigratePostController extends Controller
{
    public function __construct(
        private readonly MediaMigrationService $service,
        private readonly ExternalStorageService $storage
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'rewrite_urls' => ['nullable', 'boolean'],
            'prune_local' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
            'only' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->storage->isConfigured()) {
            return redirect()->route('admin.connections.index')
                ->with('error', 'Configura y guarda primero el almacenamiento externo.');
        }

        if ($this->service->activeRun() !== null) {
            return redirect()->route('admin.connections.index')
                ->with('error', 'Ya hay una migración de archivos en curso.');
        }

        $only = trim((string) $request->input('only', ''));
        $pruneLocal = $request->boolean('prune_local');

        $this->service->start([
            'rewrite_urls' => $request->boolean('rewrite_urls', true),
            'prune_local' => $pruneLocal,
            'force' => $request->boolean('force'),
            'dry_run' => $request->boolean('dry_run'),
            'only' => $only !== '' ? $only : null,
        ], $request->user()?->getAuthIdentifier() !== null ? (int) $request->user()->getAuthIdentifier() : null);

        $message = $pruneLocal
            ? 'Migración iniciada. Los archivos locales se borrarán solo tras verificarse cada subida.'
            : 'Migración iniciada. Se copiarán los archivos y se reescribirán las URLs (los locales se conservan).';

        return redirect()->route('admin.connections.index')->with('success', $message);
    }
}

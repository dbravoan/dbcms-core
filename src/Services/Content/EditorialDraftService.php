<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Capabilities\Contracts\ContentRevisionInspectorInterface;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use Illuminate\Database\Eloquent\Model;

final class EditorialDraftService
{
    public function __construct(private readonly ContentSnapshotService $snapshots) {}

    public function forEditor(Model $entity): Model
    {
        if (! app()->bound(ContentRevisionInspectorInterface::class)) {
            return $entity;
        }
        $revision = app(ContentRevisionInspectorInterface::class)->fingerprint($entity);
        if ($revision === null || ! is_array($revision['snapshot'] ?? null)) {
            return $entity;
        }
        $draft = $this->snapshots->hydrate($entity, $revision['snapshot']);
        $draft->setAttribute('pending_revision_id', $revision['id']);

        return $draft;
    }
}

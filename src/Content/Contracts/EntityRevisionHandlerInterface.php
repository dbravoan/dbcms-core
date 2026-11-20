<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Module-owned relationship data in trusted, server-created content revisions. */
interface EntityRevisionHandlerInterface
{
    /** @return array<string,mixed> */
    public function captureRevision(Model $entity): array;

    /** Reject incomplete module records before any canonical row is written.
     * @param  array<string,mixed>  $revision
     */
    public function validateRevision(array $revision): void;

    /** @param array<string,mixed> $revision */
    public function hydrateRevision(Model $entity, array $revision): void;

    /** @param array<string,mixed> $revision */
    public function restoreRevision(Model $entity, array $revision): void;
}

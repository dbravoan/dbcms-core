<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use DbCms\Core\DTOs\Content\EditorialDecision;
use DbCms\Core\DTOs\Content\PendingEditorialDraft;
use DbCms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

interface EditorialWorkflowInterface
{
    public function isEnabled(): bool;

    public function canPublishDirectly(?User $user = null): bool;

    public function canReview(User $user): bool;

    public function pendingReviewCount(User $user): int;

    public function requiresAudit(Model $entity, ?User $user = null): bool;

    public function latestRevisionId(Model $entity): int;

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void;

    public function recordDeletion(Model $entity, User $actor): void;

    public function pendingDraft(Model $entity): ?PendingEditorialDraft;

    /** @param array<string,mixed> $snapshot */
    public function stageDraft(Model $entity, User $actor, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): PendingEditorialDraft;

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): EditorialDecision;

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): EditorialDecision;

    public function rollback(Model $entity, int $targetVersion, User $auditor): EditorialDecision;

    public function status(Model $entity): ?string;

    public function isEditorialEnforcementEnabled(): bool;

    public function minEditorialScore(): int;
}

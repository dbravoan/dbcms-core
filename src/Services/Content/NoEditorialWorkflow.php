<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\DTOs\Content\EditorialDecision;
use DbCms\Core\DTOs\Content\PendingEditorialDraft;
use DbCms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class NoEditorialWorkflow implements EditorialWorkflowInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function canPublishDirectly(?User $user = null): bool
    {
        return false;
    }

    public function canReview(User $user): bool
    {
        return false;
    }

    public function pendingReviewCount(User $user): int
    {
        return 0;
    }

    public function requiresAudit(Model $entity, ?User $user = null): bool
    {
        return false;
    }

    public function latestRevisionId(Model $entity): int
    {
        return 0;
    }

    public function completeMutation(Model $entity, User $actor, int $previousRevisionId, ?string $editorialAction = null, ?string $notes = null): void {}

    public function recordDeletion(Model $entity, User $actor): void {}

    public function pendingDraft(Model $entity): ?PendingEditorialDraft
    {
        return null;
    }

    public function stageDraft(Model $entity, User $actor, array $snapshot, string $liveHash, bool $submit = false, ?string $notes = null): PendingEditorialDraft
    {
        throw new AccessDeniedHttpException('No editorial workflow is registered.');
    }

    public function approve(Model $entity, User $auditor, ?string $notes = null, bool $publish = false): EditorialDecision
    {
        throw new AccessDeniedHttpException('Editorial review is unavailable.');
    }

    public function reject(Model $entity, User $auditor, string $reason, ?string $notes = null): EditorialDecision
    {
        throw new AccessDeniedHttpException('Editorial review is unavailable.');
    }

    public function rollback(Model $entity, int $targetVersion, User $auditor): EditorialDecision
    {
        throw new AccessDeniedHttpException('Editorial review is unavailable.');
    }

    public function status(Model $entity): ?string
    {
        return null;
    }

    public function isEditorialEnforcementEnabled(): bool
    {
        return false;
    }

    public function minEditorialScore(): int
    {
        return 85;
    }
}

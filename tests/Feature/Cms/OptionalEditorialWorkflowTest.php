<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Content\NoEditorialWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\OptionalWorkflowEntity;

beforeEach(function (): void {
    Schema::create('optional_workflow_entities', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
    app(EntitySchemaRegistryInterface::class)->register(new EntitySchema(type: 'optional_workflow_thing', label: 'Independent entity', model: OptionalWorkflowEntity::class));
    app()->instance(EditorialWorkflowInterface::class, new NoEditorialWorkflow);
    $this->actor = User::factory()->create();
    Auth::setUser($this->actor);
    // Simulate an application without the optional module's storage, not merely
    // a configured workflow that happens to have no revision for this entity.
    Schema::dropIfExists('content_audit_revisions');
    Schema::dropIfExists('content_audits');
});

test('shared entity writes need no optional editorial tables or registered workflow', function (): void {
    $crud = app(EntityMutationService::class);
    $created = $crud->create('optional_workflow_thing', ['attributes' => ['name' => 'Initial entry', 'is_active' => true]], $this->actor);
    $updated = $crud->update('optional_workflow_thing', $created['id'], ['attributes' => ['name' => 'Updated entry']]);
    expect(OptionalWorkflowEntity::findOrFail($created['id'])->name)->toBe('Updated entry')
        ->and($updated)->not->toHaveKey('pending_revision_id');
    $deleted = $crud->delete('optional_workflow_thing', $created['id']);
    expect($deleted['deleted'])->toBeTrue()->and(OptionalWorkflowEntity::count())->toBe(0);
});

test('the absent workflow never grants approval or manufactures a private revision', function (): void {
    $workflow = app(EditorialWorkflowInterface::class);
    $entity = new OptionalWorkflowEntity;
    expect($workflow->isEnabled())->toBeFalse()->and($workflow->canPublishDirectly($this->actor))->toBeFalse()->and($workflow->pendingDraft($entity))->toBeNull()
        ->and($workflow->canReview($this->actor))->toBeFalse()->and($workflow->pendingReviewCount($this->actor))->toBe(0);
    expect(fn () => $workflow->approve($entity, $this->actor))->toThrow(AccessDeniedHttpException::class);
    expect(fn () => $workflow->reject($entity, $this->actor, 'Reason'))->toThrow(AccessDeniedHttpException::class);
    expect(fn () => $workflow->rollback($entity, 1, $this->actor))->toThrow(AccessDeniedHttpException::class);
    expect(fn () => $workflow->stageDraft($entity, $this->actor, [], 'live'))->toThrow(AccessDeniedHttpException::class);
});

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use DbCms\Core\Content\Contracts\EntityRelationMutationHandlerInterface;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Search\SearchIndexer;
use DbCms\Core\Sections\SectionSyncService;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Applies entity writes, editorial revisions and relations in one transaction. */
class EntityMutationService
{
    public function __construct(private readonly EntityReadService $reader, private readonly EntityTranslationWriter $translations) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function create(string $type, array $args, ?User $actor = null): array
    {
        $schema = $this->reader->schemaFor($type);

        return DB::transaction(function () use ($schema, $args, $actor): array {
            $entity = $schema->newModel();
            $args = $this->prepareMutation($schema, $entity, $args, true);
            $fillable = $schema->fillableAttributes();

            if (in_array('user_id', $fillable, true) && ! $actor instanceof User) {
                throw ValidationException::withMessages([
                    'type' => "Creating a [{$schema->type}] needs an author: use a user-backed token (a personal access token), not a machine client.",
                ]);
            }

            $defaults = array_map(static fn (mixed $value): mixed => $value instanceof \Closure ? $value() : $value, $schema->creationDefaults);
            $attributes = $this->arrayValue($args['attributes'] ?? []) + $defaults;
            if (array_key_exists('publish', $args)) {
                $attributes['publish'] = $args['publish'];
            }
            foreach (['is_published', 'is_active'] as $flag) {
                if (in_array($flag, $fillable, true) && ! array_key_exists($flag, $attributes)) {
                    $attributes[$flag] = false;
                }
            }
            if (in_array('slug', $fillable, true) && empty($attributes['slug'])) {
                $attributes['slug'] = Str::slug((string) ($attributes['name'] ?? $attributes['title'] ?? $schema->type)).'-'.Str::lower(Str::random(8));
            }
            $attributes = $this->prepareAttributes($schema, $attributes, $actor, creating: true);

            $entity->fill($attributes);
            $entity->save();

            $this->translations->sync($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: true);
            $this->syncRelations($schema, $entity, $args);
            $entity->refresh();
            $editor = $actor ?? Auth::user();
            if ($editor instanceof User) {
                app(EditorialWorkflowInterface::class)->completeMutation($entity, $editor, 0, is_string($args['audit_action'] ?? null) ? $args['audit_action'] : null, is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
            }

            return $this->reader->payload($schema, $entity);
        });
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function update(string $type, int|string $id, array $args): array
    {
        $schema = $this->reader->schemaFor($type);

        return DB::transaction(function () use ($schema, $id, $args): array {
            $entity = $this->reader->resolve($schema, $id, lock: true);
            $previousRevisionId = Auth::user() instanceof User ? app(EditorialWorkflowInterface::class)->latestRevisionId($entity) : 0;

            $input = $this->arrayValue($args['attributes'] ?? []);
            if (array_key_exists('publish', $args)) {
                $input['publish'] = $args['publish'];
            }
            $attributes = $this->prepareAttributes($schema, $input, null, creating: false);

            $flags = $entity->getAttributes();
            $published = (bool) ($flags['is_published'] ?? $flags['is_active'] ?? false);
            $actor = Auth::user();
            $audit = app(EditorialWorkflowInterface::class);
            $publishing = ($attributes['is_published'] ?? $attributes['is_active'] ?? false) === true;
            if ($audit->isEnabled() && $published && (! $publishing || $audit->requiresAudit($entity, $actor))) {
                abort_unless($actor instanceof User, 403);
                $snapshots = app(ContentSnapshotService::class);
                $live = $snapshots->capture($entity);

                return Model::withoutEvents(function () use ($schema, $entity, $attributes, $args, $actor, $audit, $snapshots, $live): array {
                    $previous = $audit->pendingDraft($entity);
                    $visibilityIntent = null;
                    if ($previous !== null) {
                        abort_unless(($previous->snapshot['_live_hash'] ?? null) === $snapshots->hash($live), 409, 'The live content changed after this draft was created.');
                        $snapshots->restore($entity, $previous->snapshot);
                        $visibilityIntent = $previous->snapshot['_visibility_intent'] ?? null;
                    }
                    $args = $this->prepareMutation($schema, $entity, $args, false);
                    $input = $this->arrayValue($args['attributes'] ?? []);
                    if (array_key_exists('publish', $args)) {
                        $input['publish'] = $args['publish'];
                    }
                    $attributes = $this->prepareAttributes($schema, $input, null, creating: false);
                    $entity->fill($attributes)->save();
                    $this->translations->sync($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: false);
                    $this->syncRelations($schema, $entity, $args);
                    $entity->refresh();
                    $pending = $snapshots->capture($entity);
                    foreach (['is_published', 'is_active'] as $flag) {
                        if (array_key_exists($flag, $attributes)) {
                            $visibilityIntent = ['field' => $flag, 'value' => $attributes[$flag]];
                            break;
                        }
                    }
                    if ($visibilityIntent !== null) {
                        $pending['_visibility_intent'] = $visibilityIntent;
                    }
                    $draft = $this->reader->payload($schema, $entity);
                    $record = $audit->stageDraft($entity, $actor, $pending, $snapshots->hash($live), ($args['audit_action'] ?? null) === 'submit_for_review' || ($audit->requiresAudit($entity, $actor) && ($args['audit_action'] ?? null) !== 'draft'), is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
                    $snapshots->restore($entity, $live);
                    $draft['status'] = 'draft';
                    $draft['live_preserved'] = true;
                    $draft['pending_revision_id'] = $record->id;

                    return $draft;
                });
            }
            if ($audit->isEnabled() && $publishing && $audit->pendingDraft($entity) !== null) {
                abort_unless($actor instanceof User, 403);
                $audit->approve($entity, $actor, publish: true);
            }

            $args = $this->prepareMutation($schema, $entity, $args, false);
            $input = $this->arrayValue($args['attributes'] ?? []);
            if (array_key_exists('publish', $args)) {
                $input['publish'] = $args['publish'];
            }
            $attributes = $this->prepareAttributes($schema, $input, null, creating: false);

            if ($attributes !== []) {
                $entity->fill($attributes);
                $entity->save();
            }

            $this->translations->sync($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: false);
            $this->syncRelations($schema, $entity, $args);
            $entity->refresh();
            $editor = $actor ?? Auth::user();
            if ($editor instanceof User) {
                app(EditorialWorkflowInterface::class)->completeMutation($entity, $editor, $previousRevisionId, is_string($args['audit_action'] ?? null) ? $args['audit_action'] : null, is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
            }

            return $this->reader->payload($schema, $entity);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $type, int|string $id): array
    {
        $schema = $this->reader->schemaFor($type);

        return DB::transaction(function () use ($schema, $id): array {
            $entity = $this->reader->resolve($schema, $id, lock: true);
            $snapshot = $this->reader->payload($schema, $entity);
            $actor = Auth::user();
            if ($actor instanceof User) {
                app(EditorialWorkflowInterface::class)->recordDeletion($entity, $actor);
            }

            $entity->delete();

            return [
                'deleted' => true,
                'type' => $schema->type,
                'id' => $snapshot['id'],
                'label' => $snapshot['label'] ?? null,
            ];
        });
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function prepareMutation(EntitySchema $schema, Model $entity, array $args, bool $creating): array
    {
        if ($schema->mutationHandler === null) {
            return $args;
        }
        $handler = app($schema->mutationHandler);
        if (! $handler instanceof EntityMutationHandlerInterface) {
            throw new \LogicException('Entity mutation handlers must implement the shared contract.');
        }

        return $handler->prepare($entity, $args, $creating);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepareAttributes(EntitySchema $schema, array $attributes, ?User $actor, bool $creating): array
    {
        $allowed = $schema->fillableAttributes();
        $prepared = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $allowed, true)) {
                if (in_array($key, ['is_published', 'is_active', 'is_featured'], true)) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        throw ValidationException::withMessages([$key => 'Use a boolean value.']);
                    }
                }
                $prepared[$key] = $value;
            }
        }

        // `publish` is a convenience flag mapped onto whichever publishing
        // columns the entity actually uses.
        if (array_key_exists('publish', $attributes)) {
            $publish = filter_var($attributes['publish'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($publish === null) {
                throw ValidationException::withMessages(['publish' => 'Use a boolean value.']);
            }

            if (in_array('is_published', $allowed, true)) {
                $prepared['is_published'] = $publish;

                if ($publish && in_array('published_at', $allowed, true)) {
                    $prepared['published_at'] = $attributes['published_at'] ?? now();
                }
            }

            if (in_array('is_active', $allowed, true)) {
                $prepared['is_active'] = $publish;
            }

            if (in_array('is_featured', $allowed, true) && array_key_exists('is_featured', $attributes)) {
                $prepared['is_featured'] = filter_var($attributes['is_featured'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Authorship: the acting agent replaces the backoffice session user.
        if ($creating && $actor instanceof User && in_array('user_id', $allowed, true)) {
            $prepared['user_id'] = $actor->getKey();
        } elseif (! $creating) {
            unset($prepared['user_id']);
        }

        return $prepared;
    }

    /** @param array<string,mixed> $args */
    private function syncRelations(EntitySchema $schema, Model $entity, array $args): void
    {
        $handler = $schema->mutationHandler !== null ? app($schema->mutationHandler) : null;
        if ($handler instanceof EntityRelationMutationHandlerInterface) {
            $handler->syncRelations($entity, $args);
        }
        if (! $handler instanceof EntityRelationMutationHandlerInterface && array_key_exists('market_ids', $args) && method_exists($entity, 'markets')) {
            $ids = array_values(array_unique(array_map('intval', $this->arrayValue($args['market_ids']))));
            if (Market::query()->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['market_ids' => 'Select existing markets.']);
            }
            $relation = $entity->markets();
            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
        if ($entity instanceof Sectionable && array_key_exists('sections_json', $args)) {
            $json = $args['sections_json'];
            if (is_array($json)) {
                $json = json_encode($json, JSON_THROW_ON_ERROR);
            }
            if (! is_string($json) || ! is_array(json_decode($json, true))) {
                throw ValidationException::withMessages(['sections_json' => 'Sections must be a JSON object or array.']);
            }
            $attributes = $entity->getAttributes();
            $published = (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false);
            $orderLocale = $args['sections_order_locale'] ?? null;
            if ($orderLocale !== null && ! is_string($orderLocale)) {
                throw ValidationException::withMessages(['sections_order_locale' => 'The ordering locale must be an active language code.']);
            }
            app(SectionSyncService::class)->sync($entity, $json, $published ? 'published' : 'draft', $orderLocale);
        }
        app(SectionSyncService::class)->publish($entity);
        if (array_key_exists('market_ids', $args) || array_key_exists('market_prices', $args)) {
            $entity->touchQuietly();
            $table = $entity->getTable();
            DB::afterCommit(static function () use ($table, $schema, $entity): void {
                app(PageCacheService::class)->invalidateType($table);
                app(SearchIndexer::class)->indexEntity($schema->type, $entity->getKey());
            });
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}

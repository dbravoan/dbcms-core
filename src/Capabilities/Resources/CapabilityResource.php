<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Resources;

use Closure;
use DbCms\Core\Content\EntitySchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Declarative description of a backoffice resource an agent may manage.
 *
 * Same idea as {@see EntitySchema} for content: the MCP layer
 * does not need a tool per screen, only a description of what the resource is,
 * which permission it needs, which attributes are safe to write, how to validate
 * them and which operations are allowed.
 */
readonly class CapabilityResource
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $fillable  writable keys (model-fillable ones are persisted, the rest are handed to the hooks)
     * @param  array<string, string|array<int, string>>  $rules  validation rules per attribute
     * @param  list<string>  $searchable  columns used by the free-text filter
     * @param  list<string>  $columns  attributes returned in listings (empty = fillable)
     * @param  (Closure(Builder<Model>): void)|null  $scope  extra query constraints
     * @param  (Closure(Model, array<string, mixed>): void)|null  $afterCreate
     * @param  (Closure(Model, array<string, mixed>): void)|null  $afterUpdate
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $model,
        public ?string $permission,
        public array $fillable = [],
        public array $rules = [],
        public array $searchable = [],
        public array $columns = [],
        public string $orderBy = 'id',
        public string $orderDirection = 'asc',
        public ?Closure $scope = null,
        public ?Closure $afterCreate = null,
        public ?Closure $afterUpdate = null,
        public bool $allowCreate = true,
        public bool $allowUpdate = true,
        public bool $allowDelete = true,
        public string $description = '',
        public ?string $mutationHandler = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'resource' => $this->key,
            'label' => $this->label,
            'model' => class_basename($this->model),
            'permission' => $this->permission,
            'operations' => array_keys(array_filter([
                'list' => true,
                'get' => true,
                'create' => $this->allowCreate,
                'update' => $this->allowUpdate,
                'delete' => $this->allowDelete,
            ])),
            'fillable' => $this->fillable,
            'columns' => $this->listColumns(),
            'searchable' => $this->searchable,
            'description' => $this->description,
        ];
    }

    /**
     * @return list<string>
     */
    public function listColumns(): array
    {
        return $this->columns !== [] ? $this->columns : $this->fillable;
    }
}

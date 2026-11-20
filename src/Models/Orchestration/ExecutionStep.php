<?php

declare(strict_types=1);

namespace DbCms\Core\Models\Orchestration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $id
 * @property string $execution_id
 * @property string $key
 * @property int $position
 * @property string $capability
 * @property array<string,mixed> $arguments
 * @property list<string> $dependencies
 * @property array<string,mixed>|null $resolved_arguments
 * @property string $status
 * @property string|null $binding_hash
 * @property array<string,mixed>|null $result
 * @property string|null $error
 */
class ExecutionStep extends Model
{
    protected $table = 'cms_execution_steps';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['arguments' => 'array', 'dependencies' => 'array', 'resolved_arguments' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}

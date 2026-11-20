<?php

declare(strict_types=1);

namespace DbCms\Core\Models\Orchestration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $id
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string $status
 * @property int $version
 * @property string $instruction
 * @property array<string,mixed> $actor
 * @property array<string,mixed>|null $editor_context
 * @property array<string,mixed>|null $plan
 * @property string|null $question
 * @property string|null $error
 */
class Execution extends Model
{
    protected $table = 'cms_executions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['actor', 'request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['actor' => 'array', 'plan' => 'array', 'editor_context' => 'array', 'version' => 'integer'];
    }

    /** @return HasMany<ExecutionStep,$this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ExecutionStep::class)->orderBy('position');
    }
}

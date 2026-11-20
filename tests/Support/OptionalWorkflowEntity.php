<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;

final class OptionalWorkflowEntity extends Model
{
    protected $table = 'optional_workflow_entities';

    protected $fillable = ['name', 'is_active'];
}

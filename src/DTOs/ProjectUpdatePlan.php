<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs;

final readonly class ProjectUpdatePlan
{
    /**
     * @param  list<array{path:string,old_hash:?string,new_hash:?string,action:string}>  $changes
     * @param  list<array{path:string,reason:string}>  $conflicts
     * @param  list<string>  $preserved
     * @param  list<string>  $excluded
     */
    public function __construct(public array $changes, public array $conflicts, public array $preserved, public array $excluded) {}
}

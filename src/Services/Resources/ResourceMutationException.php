<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

final class ResourceMutationException extends \RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}

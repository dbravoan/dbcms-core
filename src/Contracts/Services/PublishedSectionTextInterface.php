<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Model;

interface PublishedSectionTextInterface
{
    public function text(Model $entity, Market $market, string $locale, bool $includeWidgets = true): string;
}

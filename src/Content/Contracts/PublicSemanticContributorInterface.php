<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use DbCms\Core\Models\Market;

/** Modules own their public visibility and canonical source representation. */
interface PublicSemanticContributorInterface
{
    public function key(): string;

    public function ownerModule(): string;

    public function index(Market $market, string $locale): string;

    public function corpus(Market $market, string $locale): string;
}

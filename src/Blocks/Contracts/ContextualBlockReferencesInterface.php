<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

/** Public references eligible for a block in one explicit publication context. */
interface ContextualBlockReferencesInterface
{
    /** @return array<string, list<array<string, mixed>>> */
    public function getContextualEditorData(Market $market, string $locale): array;
}

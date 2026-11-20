<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

/** Optional metadata owned by a block, independently of any AI implementation. */
interface TranslatableBlockInterface
{
    /**
     * Explicit textual payload paths. A wildcard selects existing list items only.
     * Unlisted values, including resource references and numeric data, stay intact.
     *
     * @return list<string>
     */
    public function getTranslationPaths(): array;
}

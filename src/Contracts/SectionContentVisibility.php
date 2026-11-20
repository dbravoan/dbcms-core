<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts;

/** Entities may preserve sections while presenting content owned by code. */
interface SectionContentVisibility
{
    public function usesSectionContent(): bool;
}

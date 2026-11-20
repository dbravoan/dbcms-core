<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

/** A removed payload shape is reported, preserved and never silently reinterpreted. */
interface BlockPayloadCompatibilityInterface extends BlockTypeInterface
{
    /** @return list<string> */
    public function retiredPayloadKeys(): array;
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for entities that own an ordered, localized list of content blocks
 * (the block manager). Implemented by Page, Post and Product so the section
 * synchronization logic can live in a single, decoupled service.
 *
 * The section tables are addressed through the model classes returned below,
 * which keeps this contract free of any relation-signature coupling.
 */
interface Sectionable
{
    /**
     * @return class-string<Model>
     */
    public function sectionModelClass(): string;

    /**
     * @return class-string<Model>
     */
    public function sectionTranslationModelClass(): string;

    public function sectionForeignKey(): string;
}

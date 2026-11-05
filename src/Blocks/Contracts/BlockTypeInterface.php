<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

interface BlockTypeInterface
{
    /**
     * Unique block type identifier (e.g. 'hero', 'text', 'charts', 'roi', 'widget_posts').
     */
    public function getType(): string;

    /**
     * Human-readable name of the block.
     */
    public function getName(): string;

    /**
     * Visual icon or emoji for block pickers.
     */
    public function getIcon(): string;

    /**
     * Category grouping: 'content', 'media', 'marketing', 'interactive', 'dynamic'.
     */
    public function getCategory(): string;

    /**
     * Short description of what this block renders.
     */
    public function getDescription(): string;

    /**
     * Module identifier required for this block to be active, or null if it is a core static block.
     */
    public function getRequiredModule(): ?string;

    /**
     * Whether the block manages its own interior grid (features, pricing,
     * carousel…).
     *
     * Full-width blocks are never dropped **inside** a column: they are added as
     * a section, because nesting a grid inside a column produces a grid inside a
     * grid. Existing payloads are promoted to a section by the editor.
     */
    public function isFullWidth(): bool;

    /**
     * Default payload attributes when creating a new instance of this block.
     *
     * @return array<string, mixed>
     */
    public function getDefaultPayload(): array;

    /**
     * Renders the frontoffice HTML markup for this block.
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, ?Market $market = null, ?string $locale = null): string;

    /** @return array<string,array<string,mixed>> */
    public function getEditorSchema(): array;

    public function getEditorView(): ?string;

    /** @return array<string,mixed> */
    public function getEditorData(): array;
}

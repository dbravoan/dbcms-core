<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\Market;
use Illuminate\Support\Str;

/** One rendering path for authored sections, nested children and code-owned Blade pages. */
final class BlockRenderer
{
    private int $depth = 0;

    private int $renderedNodes = 0;

    private int $renderedBytes = 0;

    public function __construct(private readonly BlockRegistryInterface $registry) {}

    /** @param array<string,mixed> $payload */
    public function render(string $type, array $payload, ?Market $market = null, ?string $locale = null, bool $decorateLegacy = true): string
    {
        if ($this->exhausted()) {
            return '';
        }
        $render = fn (): string => $this->renderNode($type, $payload, $market, $locale, $decorateLegacy);

        return $this->registry instanceof BlockRegistry ? $this->registry->withinAvailability($render) : $render();
    }

    /** @param array<string,mixed> $payload */
    private function renderNode(string $type, array $payload, ?Market $market, ?string $locale, bool $decorateLegacy): string
    {
        if ($this->exhausted() || ! $this->registry->isAvailable($type)) {
            return '';
        }
        if (++$this->depth > (int) config('cms.blocks.max_depth', 16)) {
            $this->depth--;

            return '';
        }
        $this->renderedNodes++;
        $startingBytes = $this->renderedBytes;
        try {
            if ($market !== null && ! empty($payload['bindings'])) {
                $payload = app(BlockBindings::class)->resolve($type, $payload, $market, $locale ?? app()->getLocale());
            }
            $payload['_render_id'] = 'cms-block-'.Str::uuid();
            $html = $this->registry->render($type, $payload, $market, $locale);
            if ($html === '') {
                return '';
            }
            $output = app(BlockPresentation::class)->render($html, $decorateLegacy ? $payload : [], 'block', ['data-cms-node' => (string) ($payload['id'] ?? ''), 'data-cms-type' => $type], $payload['_render_id']);
            $ownBytes = max(0, strlen($output) - ($this->renderedBytes - $startingBytes));
            if ($this->renderedBytes + $ownBytes > $this->maxBytes()) {
                return '';
            }
            $this->renderedBytes += $ownBytes;

            return $output;

        } finally {
            $this->depth--;
        }
    }

    /** @param list<array<string,mixed>> $nodes */
    public function children(array $nodes, ?Market $market = null, ?string $locale = null): string
    {
        if ($this->exhausted()) {
            return '';
        }
        $render = fn (): string => $this->renderChildren($nodes, $market, $locale);

        return $this->registry instanceof BlockRegistry ? $this->registry->withinAvailability($render) : $render();
    }

    /** @param list<array<string,mixed>> $nodes */
    private function renderChildren(array $nodes, ?Market $market, ?string $locale): string
    {
        if ($this->depth === 0) {
            app(BlockTree::class)->assertLimits($nodes);
        }
        $html = '';
        foreach ($nodes as $node) {
            if ($this->exhausted() || ! $this->append($html, $this->render((string) ($node['type'] ?? 'text'), $node, $market, $locale))) {
                break;
            }
        }

        return $html;
    }

    /** Stops repeated-template assembly before its intermediate string exceeds the output budget. */
    public function append(string &$html, string $chunk): bool
    {
        if (strlen($html) + strlen($chunk) > $this->maxBytes()) {
            return false;
        }
        $html .= $chunk;

        return true;
    }

    /** Account for shared section/column shells in the same expanded output budget.
     * @param array<string,mixed> $payload
     * @param array<string,string|int> $attributes */
    public function presentation(string $html, array $payload, string $kind, array $attributes = []): string
    {
        $output = app(BlockPresentation::class)->render($html, $payload, $kind, $attributes);
        $ownBytes = max(0, strlen($output) - strlen($html));
        if ($this->renderedBytes + $ownBytes > $this->maxBytes()) {
            return '';
        }
        $this->renderedBytes += $ownBytes;

        return $output;
    }

    public function exhausted(): bool
    {
        return $this->renderedNodes >= (int) config('cms.blocks.max_render_nodes', 2000)
            || $this->renderedBytes >= $this->maxBytes();
    }

    private function maxBytes(): int
    {
        return max(1, (int) config('cms.blocks.max_render_bytes', 2097152));
    }
}

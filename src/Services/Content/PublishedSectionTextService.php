<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Blocks\BlockBindings;
use DbCms\Core\Blocks\BlockRegistry;
use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockTextInterface;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionLayout;
use Illuminate\Database\Eloquent\Model;

/** One extraction contract for public GEO and indexed authored block text. */
final class PublishedSectionTextService implements PublishedSectionTextInterface
{
    private int $reading = 0;

    private int $nodes = 0;

    private int $bytes = 0;

    public function __construct(private readonly PublicSemanticRegistry $widgets, private readonly BlockRegistryInterface $blocks) {}

    public function text(Model $entity, Market $market, string $locale, bool $includeWidgets = true): string
    {
        if ($this->reading === 0) {
            $this->nodes = $this->bytes = 0;
        }
        $start = $this->bytes;
        $read = fn (): string => $this->entityText($entity, $market, $locale, $includeWidgets);
        $text = $this->blocks instanceof BlockRegistry ? $this->blocks->withinAvailability($read) : $read();

        return $this->account($text, $start);
    }

    private function entityText(Model $entity, Market $market, string $locale, bool $includeWidgets): string
    {
        if (($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent()) || ! method_exists($entity, 'publishedSections')) {
            return '';
        }
        $entity->loadMissing('publishedSections.translations');
        $sections = $entity->getRelationValue('publishedSections');
        if (! is_iterable($sections)) {
            return '';
        }
        $output = '';
        if ($this->reading === 0) {
            $this->nodes = 0;
        }
        $this->reading++;
        try {
            foreach ($sections as $section) {
                if (! $section instanceof Model) {
                    continue;
                }
                $translation = PublicTranslation::resolve($section, $locale, $market);
                if (! $translation instanceof Model) {
                    continue;
                }
                $payload = SectionLayout::resolve(['type' => (string) $section->getAttribute('type')] + (array) $section->getAttribute('styling_json'), (array) $translation->getAttribute('content_json'));
                $body = $this->payloadText($payload, $market, $locale, $includeWidgets);
                if ($body !== '') {
                    $output .= "### Published section\n{$body}\n\n";
                }
            }
        } finally {
            $this->reading--;
        }

        return $output;
    }

    /** @param array<string,mixed> $payload */
    public function payloadText(array $payload, Market $market, string $locale, bool $includeWidgets = true): string
    {
        if ($this->reading === 0) {
            $this->nodes = $this->bytes = 0;
        }
        $start = $this->bytes;
        $read = fn (): string => $this->nodeText($payload, $market, $locale, $includeWidgets);
        $text = $this->blocks instanceof BlockRegistry ? $this->blocks->withinAvailability($read) : $read();

        return $this->account($text, $start);
    }

    /** @param array<string,mixed> $payload */
    private function nodeText(array $payload, Market $market, string $locale, bool $includeWidgets): string
    {
        if ($this->reading === 0) {
            $this->nodes = 0;
        }
        if ($this->bytes >= $this->maxBytes() || ! app(BlockTree::class)->supports($payload)) {
            return '';
        }
        if (++$this->nodes > (int) config('cms.blocks.max_render_nodes', 2000) || $this->reading > (int) config('cms.blocks.max_depth', 16)) {
            return '';
        }
        $this->reading++;
        try {
            $columns = SectionLayout::rawColumns($payload);
            if ($columns !== []) {
                $text = '';
                foreach ($columns as $column) {
                    foreach ((array) ($column['blocks'] ?? []) as $child) {
                        if (is_array($child) && $this->blocks->isAvailable((string) ($child['type'] ?? 'text'))) {
                            $text .= $this->payloadText($child, $market, $locale, $includeWidgets)."\n";
                        }
                    }
                }

                return $text;
            }
            $type = (string) ($payload['type'] ?? 'text');
            if (! $this->blocks->isAvailable($type)) {
                return '';
            }
            $definition = $this->blocks->get($type);
            if (! empty($payload['bindings'])) {
                if ($includeWidgets) {
                    $payload = app(BlockBindings::class)->resolve($type, $payload, $market, $locale);
                } else {
                    foreach (array_keys((array) $payload['bindings']) as $key) {
                        unset($payload[$key]);
                    }
                }
            }
            if ($definition instanceof ContextualBlockTextInterface) {
                return $definition->text($payload, $market, $locale, $includeWidgets);
            }
            $text = SectionLayout::plainText($payload, ['text', 'title', 'headline', 'content', 'body', 'summary', 'subtitle', 'description', 'question', 'answer', 'quote', 'author', 'caption', 'alt', 'label', 'value', 'note', 'period', 'amount', 'currency', 'previous_amount', 'prefix', 'suffix', 'unit']);
            foreach (app(BlockTree::class)->slots($payload) as $slot) {
                foreach ($slot['nodes'] as $child) {
                    if ($this->blocks->isAvailable((string) ($child['type'] ?? 'text'))) {
                        $text .= "\n".$this->payloadText($child, $market, $locale, $includeWidgets);
                    }
                }
            }
            if ($includeWidgets) {
                $text .= $this->widgets->widgetText($payload, $market, $locale);
            }

            return $text;
        } finally {
            $this->reading--;
        }
    }

    /** Descendant text is already charged; count only this node's extra output. */
    private function account(string $text, int $start): string
    {
        $ownBytes = max(0, strlen($text) - ($this->bytes - $start));
        $remaining = max(0, $this->maxBytes() - $this->bytes);
        if ($ownBytes > $remaining) {
            $text = mb_strcut($text, 0, max(0, strlen($text) - ($ownBytes - $remaining)), 'UTF-8');
            $ownBytes = max(0, strlen($text) - ($this->bytes - $start));
        }
        $this->bytes += $ownBytes;

        return $text;
    }

    private function maxBytes(): int
    {
        return max(1, (int) config('cms.blocks.max_render_bytes', 2097152));
    }
}

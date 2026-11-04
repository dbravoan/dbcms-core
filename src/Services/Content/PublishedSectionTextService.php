<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionLayout;
use Illuminate\Database\Eloquent\Model;

/** One extraction contract for public GEO and indexed authored block text. */
final class PublishedSectionTextService implements PublishedSectionTextInterface
{
    public function __construct(private readonly PublicSemanticRegistry $widgets, private readonly BlockRegistryInterface $blocks) {}

    public function text(Model $entity, Market $market, string $locale, bool $includeWidgets = true): string
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
        foreach ($sections as $section) {
            if (! $section instanceof Model) {
                continue;
            }
            $translation = PublicTranslation::resolve($section, $locale, $market);
            if (! $translation instanceof Model) {
                continue;
            }
            $payload = SectionLayout::resolve((array) $section->getAttribute('styling_json'), (array) $translation->getAttribute('content_json'));
            $visible = array_values(array_filter(SectionLayout::blocks($payload), fn (array $block): bool => $this->blocks->isAvailable((string) ($block['type'] ?? 'text'))));
            $payload = ['columns' => [['blocks' => $visible]]];
            $body = SectionLayout::plainText($payload, ['title', 'headline', 'content', 'body', 'summary', 'subtitle', 'description', 'question', 'answer', 'caption', 'alt', 'label', 'value']);
            if ($includeWidgets) {
                $body .= $this->widgetTexts($payload, $market, $locale);
            }
            if ($body !== '') {
                $output .= "### Published section\n{$body}\n\n";
            }
        }

        return $output;
    }

    /** @param array<string,mixed> $payload */
    private function widgetTexts(array $payload, Market $market, string $locale): string
    {
        $text = $this->widgets->widgetText($payload, $market, $locale);
        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $text .= $this->widgetTexts($child, $market, $locale);
                    }
                }
            } else {
                $text .= $this->widgetTexts($value, $market, $locale);
            }
        }

        return $text;
    }
}

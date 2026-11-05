<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Search;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\ContextualSearchSource;
use DbCms\Core\Search\SearchDocument;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PageSearchSource extends ContextualSearchSource
{
    public function type(): string
    {
        return 'page';
    }

    public function label(string $locale): string
    {
        return match ($locale) {
            'en' => 'Page', 'ca' => 'Pàgina', 'fr' => 'Page', default => 'Página'
        };
    }

    /** @return Builder<Page> */
    protected function query(): Builder
    {
        // Prepare scheduled publications internally; SearchEngine revalidates the deadline.
        return Page::query()->where('is_published', true)->with(['translations', 'markets']);
    }

    protected function document(Model $entity, Model $translation, Market $market): ?SearchDocument
    {
        $resolved = PublicTranslation::resolve($entity, (string) $translation->getAttribute('locale'), $market);
        if ($resolved === null || $resolved->getKey() !== $translation->getKey()) {
            return null;
        }
        $translation = $resolved;
        $slug = (string) $translation->getAttribute('slug');
        $title = (string) $translation->getAttribute('title');
        $locale = (string) $translation->getAttribute('locale');
        if (trim($slug) === '' || trim($title) === '') {
            return null;
        }

        return new SearchDocument($this->type(), (string) $entity->getKey(), $locale,
            $this->discovery->url($this->type(), $market, $locale, $slug), $title,
            excerpt: (string) ($translation->getAttribute('meta_description') ?? ''),
            body: mb_substr(app(PublishedSectionTextInterface::class)->text($entity, $market, $locale, false), 0, 20000),
            market: $market->code);
    }
}

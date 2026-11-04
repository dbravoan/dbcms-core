<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\DTOs\Content\EntityMutationData;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Content\EditorialDraftService;
use Illuminate\Support\Facades\Auth;

class PageDomainService implements PageDomainServiceInterface
{
    /**
     * Resolves a published page by its localized slug for the given market and locale.
     */
    public function resolvePageBySlug(string $slug, ?Market $market = null, ?string $locale = null): ?Page
    {
        $locale = $locale ?? app()->getLocale();

        $page = Page::published()
            ->forMarket($market)
            ->forPublicTranslation($market, $locale, $slug)
            ->with('translations')
            ->first();

        if ($page) {
            return $page;
        }

        // Fallback for homepage requests: return primary published page
        if ($slug === 'home' || $slug === 'inicio' || $slug === '') {
            return Page::published()
                ->forMarket($market)
                ->forPublicTranslation($market, $locale)
                ->with('translations')
                ->first();
        }

        return null;
    }

    public function __construct(private readonly EntityMutationService $mutations, private readonly EntityReadService $reader) {}

    /** @param array<string,mixed> $data */
    public function createPage(array $data, ?User $actor = null): Page
    {
        $actor ??= Auth::user();
        $actor ??= app(CapabilityAuthorizer::class)->user(app(ExecutionContext::class)->actor ?? new ActorContext(null));
        abort_unless($actor instanceof User, 403);
        $payload = $this->mutations->create('page', EntityMutationData::fromForm($data)->arguments, $actor);
        $page = $this->reader->resolveEntity('page', $payload['id']);
        assert($page instanceof Page);

        return $page->load($page->usesSectionContent() ? ['translations', 'sections.translations', 'markets'] : ['translations', 'markets']);
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updatePage(Page $page, array $data): array
    {
        $arguments = EntityMutationData::fromForm($data)->arguments;
        if ($page->is_published && ! empty($arguments['attributes']['is_published'])) {
            unset($arguments['attributes']['is_published'], $arguments['attributes']['published_at']);
        }

        return $this->mutations->update('page', $page->getKey(), $arguments);
    }

    public function deletePage(Page $page): void
    {
        $this->mutations->delete('page', $page->getKey());
    }

    /** @return array<string,mixed> */
    public function editData(Page $page): array
    {
        $page->load(['translations', 'markets']);
        $liveUrl = app(PageAdminPublicLinks::class)->forPages(collect([$page]))[$page->id] ?? null;
        $page = app(EditorialDraftService::class)->forEditor($page);
        assert($page instanceof Page);
        $data = [
            'page' => $page,
            'liveUrl' => $liveUrl,
            'sectionsData' => $page->usesSectionContent() ? app(SectionEditorService::class)->payload($page) : [],
            'pageViews' => app(PageViewRegistry::class)->all(),
            'markets' => Market::with('languages')->where('is_active', true)->get(),
            'languages' => Language::where('is_active', true)->get(),
        ];

        return $data;
    }
}

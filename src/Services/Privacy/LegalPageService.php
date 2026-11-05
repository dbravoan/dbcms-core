<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Privacy;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

final class LegalPageService
{
    public const LOCALES = ['es', 'en', 'ca'];

    public function __construct(private readonly GeneralSettingsServiceInterface $settings) {}

    /** @return array<string,mixed> */
    public function company(): array
    {
        $company = [];
        foreach (['company_name', 'commercial_activity', 'registry_required', 'dpo_required', 'tax_id', 'address', 'country', 'contact_email', 'privacy_email', 'activity', 'commercial_registry', 'dpo_contact', 'hosting_provider', 'hosting_country', 'processing'] as $key) {
            $company[$key] = $this->settings->get('legal.'.$key, $key === 'processing' ? [] : '');
        }

        return $company;
    }

    /** @param array<array-key,mixed> $adoptions Reviewed role => existing Page ID selections. */
    public function provision(LegalCompanyDTO $company, array $adoptions = []): void
    {
        $locales = array_values(Language::query()->where('is_active', true)->pluck('code')->map(static fn (mixed $code): string => (string) $code)->all());
        if ($locales === [] || array_filter($locales, fn (string $locale): bool => ! $this->supportsLocale($locale)) !== []) {
            throw new \DomainException('Legal templates are available for es, en and ca. Prepare legal translations before activating another language.');
        }
        DB::transaction(function () use ($company, $locales, $adoptions): void {
            $pages = $this->planPages($locales, $adoptions);
            foreach ($company->values as $key => $value) {
                $this->settings->set('legal.'.$key, $value, 'legal', is_array($value) ? 'json' : (is_bool($value) ? 'boolean' : 'string'));
            }
            $this->settings->set('legal.reviewed_at', now()->toIso8601String(), 'legal');
            $menu = Menu::firstOrCreate(['slug' => 'footer_legal'], ['name' => 'Legal', 'location' => 'footer_legal', 'is_active' => true]);
            foreach (['notice', 'privacy', 'cookies'] as $order => $kind) {
                $page = $pages[$kind];
                if ($page === null) {
                    $page = Page::create(['template' => 'blade:legal.'.$kind, 'layout' => 'default', 'is_published' => true, 'published_at' => now()]);
                } elseif ($page->template !== 'blade:legal.'.$kind) {
                    $page->fill(['template' => 'blade:legal.'.$kind])->save();
                }
                $item = $menu->items()->firstOrCreate(['type' => 'page', 'target_id' => $page->id], ['order' => $order, 'is_active' => true, 'target_blank' => false]);
                foreach ($locales as $locale) {
                    $title = (string) trans('legal.'.$kind, [], $locale);
                    $slug = (string) trans('legal.slugs.'.$kind, [], $locale);
                    $translation = $page->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['title' => $title, 'slug' => $slug, 'meta_title' => $title, 'schema_type' => 'WebPage']);
                    $item->translations()->firstOrCreate(['locale' => $locale], ['title' => $translation->title]);
                }
                $slug = $page->translations()->whereNull('market_id')->where('locale', (string) config('app.locale', 'es'))->value('slug')
                    ?? $page->translations()->whereNull('market_id')->where('locale', $locales[0])->value('slug');
                $this->settings->set('gdpr.page_'.$kind, (string) $slug, 'gdpr');
            }
        });
    }

    /** @param list<string> $locales
     * @param  array<array-key,mixed>  $adoptions
     * @return array<string,Page|null>
     */
    private function planPages(array $locales, array $adoptions): array
    {
        $roles = ['notice', 'privacy', 'cookies'];
        foreach ($adoptions as $role => $id) {
            if (! in_array($role, $roles, true) || ! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['adopt_legal_pages' => 'Use notice/privacy/cookies keys and positive integer Page IDs.']);
            }
        }
        if (count(array_unique($adoptions)) !== count($adoptions)) {
            throw ValidationException::withMessages(['adopt_legal_pages' => 'Each legal role requires a distinct Page ID.']);
        }
        $pages = [];
        foreach ($roles as $role) {
            $existing = Page::query()->where('template', 'blade:legal.'.$role)->lockForUpdate()->get();
            if ($existing->count() > 1) {
                throw ValidationException::withMessages(['adopt_legal_pages' => 'Multiple pages already claim legal role '.$role.'. Review them before continuing.']);
            }
            $page = $existing->first();
            if (isset($adoptions[$role])) {
                if ($page !== null && $page->id !== $adoptions[$role]) {
                    throw ValidationException::withMessages(['adopt_legal_pages' => 'Legal role '.$role.' already belongs to Page '.$page->id.'.']);
                }
                $page = Page::query()->lockForUpdate()->find($adoptions[$role]);
                if ($page === null || (str_starts_with($page->template, 'blade:legal.') && $page->template !== 'blade:legal.'.$role)) {
                    throw ValidationException::withMessages(['adopt_legal_pages' => 'The selected Page is missing or belongs to another legal role.']);
                }
                if (! $page->is_published || $page->published_at === null || $page->published_at->isFuture() || $page->markets()->exists()) {
                    throw ValidationException::withMessages(['adopt_legal_pages' => 'An adopted legal page must already be published and available to every market.']);
                }
                foreach ($locales as $locale) {
                    $translation = $page->translations()->whereNull('market_id')->where('locale', $locale)->first();
                    if ($translation === null || trim($translation->title) === '' || trim($translation->slug) === '') {
                        throw ValidationException::withMessages(['adopt_legal_pages' => 'Prepare a global title and slug for every active locale before adopting Page '.$page->id.'.']);
                    }
                }
            } elseif ($page === null) {
                $configured = trim((string) $this->settings->get('gdpr.page_'.$role, ''), ' /');
                $slugs = array_map(static fn (string $locale): string => (string) trans('legal.slugs.'.$role, [], $locale), $locales);
                if ($configured !== '') {
                    $slugs[] = $configured;
                }
                $candidates = PageTranslation::query()->whereIn('slug', array_unique($slugs))->distinct()->pluck('page_id')->all();
                if ($candidates !== []) {
                    throw ValidationException::withMessages(['adopt_legal_pages' => 'Existing '.$role.' page candidates ['.implode(', ', $candidates).'] require an explicit --adopt-legal-pages mapping. No legal page was changed.']);
                }
            }
            foreach ($locales as $locale) {
                $slug = $page?->translations()->whereNull('market_id')->where('locale', $locale)->value('slug') ?? (string) trans('legal.slugs.'.$role, [], $locale);
                $collision = PageTranslation::query()->where('locale', $locale)->where('slug', $slug);
                if ($page !== null) {
                    $collision->where('page_id', '!=', $page->id);
                }
                if ($collision->exists()) {
                    throw ValidationException::withMessages(['adopt_legal_pages' => 'Legal slug '.$slug.' ('.$locale.') is already used by another page. Review the collision before continuing.']);
                }
            }
            $pages[$role] = $page;
        }

        return $pages;
    }

    public function supportsLocale(string $locale): bool
    {
        foreach (['legal', 'privacy'] as $group) {
            $reference = Lang::get($group, [], 'es', false);
            if (! is_array($reference)) {
                return false;
            }
            foreach (array_keys(Arr::dot($reference)) as $key) {
                if (! Lang::hasForLocale($group.'.'.$key, $locale)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string,mixed>|null */
    public function processing(string $owner): ?array
    {
        foreach ((array) ($this->company()['processing'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['owner'] ?? null) === $owner) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function requireProcessing(string $owner, bool $allowManualReview = false): array
    {
        $entry = $this->processing($owner);
        $days = $entry['retention_days'] ?? null;
        $finite = filter_var($days, FILTER_VALIDATE_INT) !== false && (int) $days > 0;
        $manual = $allowManualReview && ($entry['manual_review'] ?? false) === true;
        if ($entry === null || (! $finite && ! $manual)) {
            throw ValidationException::withMessages(['privacy' => __('legal.collection_unavailable')]);
        }
        foreach (['purpose', 'legal_basis', 'data', 'retention', 'recipients', 'transfers'] as $key) {
            if (! is_string($entry[$key] ?? null) || trim($entry[$key]) === '') {
                throw ValidationException::withMessages(['privacy' => __('legal.collection_unavailable')]);
            }
        }

        return $entry;
    }

    public function canCollect(string $owner, bool $allowManualReview = false): bool
    {
        try {
            $this->requireProcessing($owner, $allowManualReview);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function privacyUrl(): string
    {
        $page = Page::query()->where('template', 'blade:legal.privacy')->with('translations')->first();
        $market = app()->bound('current.market') ? app('current.market') : null;
        $market = $market instanceof Market ? $market : null;
        $slug = $page !== null ? PublicTranslation::resolve($page, app()->getLocale(), $market)?->getAttribute('slug') : trans('legal.slugs.privacy');

        return app(LocalizationServiceInterface::class)->url('/'.$slug, $market);
    }

    /** @param array<string,mixed> $attributes */
    public function assertPageMutation(Page $page, array $attributes, bool $deleting = false): void
    {
        $original = $page->getRawOriginal('template');
        if (! $page->exists || ! in_array($original, ['blade:legal.notice', 'blade:legal.privacy', 'blade:legal.cookies'], true)) {
            return;
        }
        $template = $attributes['template'] ?? $original;
        $published = $attributes['is_published'] ?? $page->is_published;
        $date = array_key_exists('published_at', $attributes) ? $attributes['published_at'] : $page->published_at;
        if ($deleting || $template !== $original || ! $published || $date === null || Carbon::parse($date)->isFuture()) {
            throw ValidationException::withMessages(['page' => 'Required legal pages must remain published with their reserved template.']);
        }
    }

    /** @param array<string,mixed> $snapshot */
    public function assertLegalSnapshot(Page $page, array $snapshot): void
    {
        $this->assertPageMutation($page, (array) $snapshot['attributes']);
        if (! in_array($page->getRawOriginal('template'), ['blade:legal.notice', 'blade:legal.privacy', 'blade:legal.cookies'], true)) {
            return;
        }
        $prepared = [];
        foreach ((array) ($snapshot['translation_records'] ?? []) as $translation) {
            if (is_array($translation) && ! empty($translation['title']) && ! empty($translation['slug'])) {
                $prepared[] = $translation['locale'] ?? '';
            }
        }
        $active = Language::query()->where('is_active', true)->pluck('code')->all();
        if (array_diff($active, $prepared) !== [] || ($snapshot['market_ids'] ?? []) !== []) {
            throw ValidationException::withMessages(['page' => 'Required legal pages must remain available in every active locale and market.']);
        }
    }

    public function prepareLocale(string $locale): void
    {
        if (! $this->supportsLocale($locale)) {
            throw new \DomainException('Prepare complete legal translations before activating this language.');
        }
        foreach (['notice', 'privacy', 'cookies'] as $kind) {
            $page = Page::query()->where('template', 'blade:legal.'.$kind)->first();
            if ($page === null) {
                continue;
            }
            $title = (string) trans('legal.'.$kind, [], $locale);
            $page->translations()->firstOrCreate(['locale' => $locale, 'market_id' => null], ['title' => $title, 'slug' => trans('legal.slugs.'.$kind, [], $locale), 'meta_title' => $title, 'schema_type' => 'WebPage']);
            foreach (MenuItem::query()->where('type', 'page')->where('target_id', $page->id)->get() as $item) {
                $item->translations()->firstOrCreate(['locale' => $locale], ['title' => $title]);
            }
        }
    }
}

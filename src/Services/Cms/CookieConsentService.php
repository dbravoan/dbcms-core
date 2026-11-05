<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Security\Contracts\CookieDisclosureContributorInterface;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** One versioned, server-owned decision shared by browser and outbound integrations. */
class CookieConsentService
{
    public const COOKIE = 'cms_cookie_consent';

    public const DAYS = 180;

    /** @param iterable<CookieDisclosureContributorInterface> $contributors */
    public function __construct(#[Tag('cms.cookie-disclosures')] private readonly iterable $contributors = []) {}

    /** @return array<string, array{key:string,title:string,description:string,required:bool,default:bool,cookies:array<int,array{name:string,provider:string,purpose:string,duration:string}>}> */
    public function getDisclosedCategories(?string $locale = null): array
    {
        $categories = $this->baseCategories($locale ?? app()->getLocale());
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->categories($locale ?? app()->getLocale()) as $category) {
                $key = $category['key'];
                $previous = $categories[$key] ?? null;
                $categories[$key] = [
                    'key' => $key,
                    'title' => $key === 'necessary' ? $categories['necessary']['title'] : $category['title'],
                    'description' => trim(($previous['description'] ?? '').' '.$category['description']),
                    'required' => $key === 'necessary',
                    'default' => $key === 'necessary',
                    'cookies' => array_values(array_merge($previous['cookies'] ?? [], $category['cookies'])),
                ];
            }
        }

        return $categories;
    }

    /** @return array<string, array{key:string,title:string,description:string,required:bool,default:bool,cookies:array<int,array{name:string,provider:string,purpose:string,duration:string}>}> */
    private function baseCategories(string $locale): array
    {
        $categories = [
            'necessary' => ['key' => 'necessary', 'title' => (string) trans('privacy.necessary_title', [], $locale), 'description' => (string) trans('privacy.necessary_description', [], $locale), 'required' => true, 'default' => true, 'cookies' => [
                ['name' => (string) config('session.cookie', 'laravel_session'), 'provider' => 'dbCMS', 'purpose' => (string) trans('privacy.session', [], $locale), 'duration' => ((int) config('session.lifetime', 120)).' min'],
                ['name' => 'XSRF-TOKEN', 'provider' => 'dbCMS', 'purpose' => 'CSRF', 'duration' => ((int) config('session.lifetime', 120)).' min'],
                ['name' => self::COOKIE, 'provider' => 'dbCMS', 'purpose' => (string) trans('privacy.choice', [], $locale), 'duration' => self::DAYS.' '.trans('privacy.days', [], $locale)],
                ['name' => 'cms_geo_preference', 'provider' => 'dbCMS', 'purpose' => (string) trans('privacy.geo', [], $locale), 'duration' => (string) trans('privacy.year', [], $locale)],
            ]],

        ];
        foreach (app(ExternalContentPolicy::class)->assetServices() as $service) {
            $categories['necessary']['description'] .= ' '.$service['provider'].': '.$service['purpose'].' ('.implode(', ', $service['origins']).').';
        }
        $services = app(ExternalContentPolicy::class)->services();
        if ($services !== []) {
            $details = [];
            foreach ($services as $service) {
                $details[] = $service['provider'].': '.$service['purpose'].' ('.implode(', ', $service['origins']).')';
            }
            $categories['external'] = ['key' => 'external', 'title' => (string) trans('privacy.external_title', [], $locale), 'description' => implode('. ', $details), 'required' => false, 'default' => false, 'cookies' => []];
        }

        return $categories;
    }

    public function revision(): string
    {
        $integrations = [];
        foreach ($this->contributors as $contributor) {
            $data = $contributor->revisionData();
            if ($data !== []) {
                $integrations[$contributor::class] = $data;
            }
        }
        ksort($integrations);

        return hash('sha256', json_encode(['schema' => 1, 'integrations' => $integrations, 'policy' => app(LegalPageService::class)->company(), 'categories' => $this->getDisclosedCategories('en'), 'external_services' => app(ExternalContentPolicy::class)->services(), 'asset_services' => app(ExternalContentPolicy::class)->assetServices()], JSON_THROW_ON_ERROR));
    }

    /** @return array<string,bool> */
    public function defaults(): array
    {
        return array_map(static fn (array $category): bool => $category['required'], $this->getDisclosedCategories());
    }

    /** @return array<string,mixed>|null */
    private function receipt(?Request $request = null): ?array
    {
        return $this->receiptById(($request ?? request())->cookie(self::COOKIE));
    }

    /** @return array<string,mixed>|null */
    private function receiptById(mixed $id): ?array
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }
        $record = DB::table('privacy_consents')->where('id', $id)->where('revision', $this->revision())->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if ($record === null) {
            return null;
        }
        $preferences = json_decode((string) $record->preferences, true);

        return is_array($preferences) ? $preferences : null;
    }

    public function hasGivenConsent(?Request $request = null): bool
    {
        return $this->receipt($request) !== null;
    }

    public function hasConsent(string $category, ?Request $request = null): bool
    {
        return $this->getConsentPreferences($request)[$category] ?? false;
    }

    /** Revalidate an opaque request receipt in a worker immediately before an external effect. */
    public function hasConsentForReceipt(string $category, string $receiptId): bool
    {
        return $this->preferences($this->receiptById($receiptId))[$category] ?? false;
    }

    /** @return array<string,bool> */
    public function getConsentPreferences(?Request $request = null): array
    {
        return $this->preferences($this->receipt($request));
    }

    /** @param array<string,mixed>|null $record
     * @return array<string,bool>
     */
    private function preferences(?array $record): array
    {
        $defaults = $this->defaults();
        foreach ($defaults as $key => $required) {
            $defaults[$key] = $required || ($record[$key] ?? false) === true;
        }

        return $defaults;
    }

    /** @param array<string,bool> $preferences
     * @return array{id:string,revision:string,preferences:array<string,bool>,expires_at:string}
     */
    public function record(array $preferences): array
    {
        $accepted = $this->defaults();
        foreach ($accepted as $key => $required) {
            $accepted[$key] = $required || ($preferences[$key] ?? false) === true;
        }
        $id = (string) Str::uuid();
        $revision = $this->revision();
        $expires = now()->addDays(self::DAYS);
        DB::transaction(function () use ($id, $revision, $accepted, $expires): void {
            $previous = request()->cookie(self::COOKIE);
            if (is_string($previous) && Str::isUuid($previous)) {
                DB::table('privacy_consents')->where('id', $previous)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            DB::table('privacy_consents')->insert(['id' => $id, 'revision' => $revision, 'preferences' => json_encode($accepted, JSON_THROW_ON_ERROR), 'recorded_at' => now(), 'expires_at' => $expires]);
        });

        return ['id' => $id, 'revision' => $revision, 'preferences' => $accepted, 'expires_at' => $expires->toIso8601String()];
    }
}

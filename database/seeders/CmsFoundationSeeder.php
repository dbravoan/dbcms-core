<?php

declare(strict_types=1);

namespace DbCms\Core\Database\Seeders;

use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * The minimum a working installation needs: the language matrix, the market
 * matrix and a Passport personal access client.
 *
 * Idempotent (`firstOrCreate`) and free of demo content: the narrative demo
 * lives in {@see PlatformShowcaseSeeder}. Module installation is an explicit
 * operation owned by the installer, never by this foundation seeder.
 */
class CmsFoundationSeeder extends Seeder
{
    /**
     * Languages: code => [name, native, direction, default].
     *
     * New projects start with Spanish. Explicit workbench fixtures provide
     * additional language/market matrices when those behaviors are tested.
     *
     * @var array<string, array{name: string, native: string, direction: string, default: bool}>
     */
    public const LANGUAGES = [
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'direction' => 'ltr', 'default' => true],
    ];

    /**
     * Markets: code => [name, currency, default locale, default market, locales].
     *
     * @var array<string, array{name: string, currency: string, default_locale: string, default: bool, locales: list<string>}>
     */
    public const MARKETS = [
        'es' => ['name' => 'Spain', 'currency' => 'EUR', 'default_locale' => 'es', 'default' => true, 'locales' => ['es']],
    ];

    public function run(): void
    {
        $languages = [];
        foreach (static::LANGUAGES as $code => $definition) {
            $languages[$code] = Language::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'native_name' => $definition['native'],
                    'direction' => $definition['direction'],
                    'is_default' => $definition['default'] && ! Language::query()->where('is_default', true)->exists(),
                    'is_active' => true,
                ]
            );
        }

        foreach (static::MARKETS as $code => $definition) {
            $market = Market::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'currency' => $definition['currency'],
                    'default_locale' => $definition['default_locale'],
                    'is_default' => $definition['default'] && ! Market::query()->where('is_default', true)->exists(),
                    'is_active' => true,
                ]
            );

            $sync = [];
            foreach ($definition['locales'] as $locale) {
                if (! isset($languages[$locale])) {
                    continue;
                }

                $sync[$languages[$locale]->id] = ['is_default' => $locale === $market->default_locale];
            }

            // Existing markets may have a customized default and additional languages.
            $defaultLanguage = Language::query()->where('code', $market->default_locale)->first();
            if ($defaultLanguage !== null) {
                $sync[$defaultLanguage->id] = ['is_default' => true];
            }
            $market->languages()->syncWithoutDetaching($sync);
            $market->languages()->newPivotStatement()->where('market_id', $market->id)
                ->where('language_id', '!=', $defaultLanguage->id ?? 0)->update(['is_default' => false]);
        }

        $this->ensurePersonalAccessClient();
    }

    /**
     * Ensure a Passport personal access client exists so `cms:issue-mcp-token`
     * (and the backoffice "generate token" action) work on a fresh install.
     */
    protected function ensurePersonalAccessClient(): void
    {
        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        $provider = (string) config('auth.guards.api.provider', 'users');
        $usable = Client::query()->where('revoked', false)
            ->where(fn ($query) => $query->where('provider', $provider)->orWhereNull('provider'))
            ->get()->contains(fn (Client $client): bool => $client->hasGrantType('personal_access'));

        if (! $usable) {
            app(ClientRepository::class)->createPersonalAccessGrantClient(
                (string) config('cms.name', 'CMS').' Personal Access Client', $provider
            );
        }
    }
}

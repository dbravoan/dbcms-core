<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Admin;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\MenuEditorServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\DTOs\Admin\MenuEditorData;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class MenuEditorService implements MenuEditorServiceInterface
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
        private readonly DatatableQueryService $tables,
        private readonly PublicContentDiscoveryInterface $discovery
    ) {}

    public function index(DatatableQueryData $input): MenuEditorData
    {
        $query = Menu::with(['items.translations', 'markets']);

        $menus = $this->tables->paginate(
            $query,
            $input,
            ['name', 'slug', 'location'],
            ['location' => 'location'],
            'created_at',
            'desc'
        );

        $markets = Market::where('is_active', true)->get();

        $availableEntities = [];
        $locales = $this->localization->activeLocales();
        $defaultLocale = $this->localization->defaultLocale();

        $entityTitle = static function (Model $entity, string $locale, string $defaultLocale, string $fallback): string {
            /** @var Collection<int, Model> $translations */
            $translations = $entity->getRelation('translations');

            $translation = $translations->where('locale', $locale)->first()
                ?? $translations->where('locale', $defaultLocale)->first()
                ?? $translations->first();

            $title = (string) ($translation->title ?? $translation->name ?? '');

            return $title !== '' ? $title : $fallback;
        };

        $pushEntity = static function (array $row, array $locales, callable $titleFor) use (&$availableEntities): void {
            $titles = [];
            foreach ($locales as $locale) {
                $titles["title_{$locale}"] = $titleFor($locale);
            }

            $availableEntities[] = $row + $titles;
        };

        foreach ($this->discovery->schemas() as $schema) {
            if ($schema->publicContent === null) {
                continue;
            }
            $seen = [];
            foreach ($this->discovery->markets() as $market) {
                foreach ($market->languages as $language) {
                    foreach ($this->discovery->query($schema->type, $market, $language->code)->latest()->limit(40)->get() as $entity) {
                        if (isset($seen[$entity->getKey()])) {
                            continue;
                        }
                        $seen[$entity->getKey()] = true;
                        $pushEntity([
                            'type' => $schema->type,
                            'id' => $entity->getKey(),
                            'url' => $this->discovery->entityUrl($schema->type, $entity, $market, $language->code),
                            'icon' => $schema->publicContent->icon,
                            'badge' => $schema->label,
                        ], $locales, static fn (string $locale): string => $entityTitle($entity, $locale, $defaultLocale, $schema->label.' #'.$entity->getKey()));
                    }
                }
            }
        }

        // Entities carry a `label` in the default language for the picker list.
        foreach ($availableEntities as $index => $entity) {
            $availableEntities[$index]['label'] = $entity['title_'.$defaultLocale] ?? reset($entity) ?: '';
        }

        $registeredZones = [
            'header' => [
                'name' => 'Cabecera Principal (Header)',
                'description' => 'Navegación superior principal visible en todas las páginas',
                'icon' => 'fa-solid fa-heading',
            ],
            'footer_quick' => [
                'name' => 'Pie de Página: Enlaces Rápidos',
                'description' => 'Columna de navegación secundaria en el pie de página',
                'icon' => 'fa-solid fa-shoe-prints',
            ],
            'footer_legal' => [
                'name' => 'Pie de Página: Legal & Transparencia',
                'description' => 'Enlaces obligatorios (Aviso Legal, Privacidad, Cookies)',
                'icon' => 'fa-solid fa-scale-balanced',
            ],
            'sidebar' => [
                'name' => 'Barra Lateral (Sidebar)',
                'description' => 'Menú contextual para páginas internas, categorías y blog',
                'icon' => 'fa-solid fa-table-columns',
            ],
            'topbar' => [
                'name' => 'Barra Superior (Topbar)',
                'description' => 'Pre-cabecera de utilidades, idiomas y accesos directos',
                'icon' => 'fa-solid fa-window-minimize',
            ],
            'mobile' => [
                'name' => 'Menú Móvil / Drawer',
                'description' => 'Navegación adaptada a dispositivos móviles y táctiles',
                'icon' => 'fa-solid fa-mobile-screen',
            ],
        ];

        $allMenus = Menu::with('items')->orderBy('name')->get();

        $zoneAssignments = [];
        foreach (array_keys($registeredZones) as $zoneKey) {
            $assigned = $allMenus->firstWhere('location', $zoneKey);
            $zoneAssignments[$zoneKey] = $assigned?->id;
        }

        return new MenuEditorData([
            'menus' => $menus,
            'allMenus' => $allMenus,
            'registeredZones' => $registeredZones,
            'zoneAssignments' => $zoneAssignments,
            'markets' => $markets,
            'availableEntities' => $availableEntities,
            'activeTab' => $input->input('tab', 'menus'),
            'languages' => $this->localization->activeLanguages(),
            'languageFlags' => $this->localization->activeLanguages()
                ->mapWithKeys(static fn ($language): array => [$language->code => $language->flag])
                ->all(),
            'defaultLocale' => $defaultLocale,
        ]);
    }
}

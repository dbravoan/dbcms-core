<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Services\Content\SafeContentRenderer;
use Illuminate\Database\Eloquent\Model;

final class MenuItemMutationHandler extends AbstractResourceMutationHandler
{
    public function __construct(private readonly LocalizationServiceInterface $localization, private readonly SafeContentRenderer $safe) {}

    public function rules(Model $model, bool $creating): array
    {
        return ['menu_id' => ['required', 'integer', 'exists:menus,id'], 'type' => ['required', 'string', 'max:40'], 'url' => ['required', 'string', 'max:500'], 'order' => ['nullable', 'integer', 'min:0'], 'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'], 'target_id' => ['nullable', 'integer', 'min:1'], 'is_active' => ['boolean'], 'target_blank' => ['boolean'], 'translations' => ['array'], 'translations.*.title' => ['nullable', 'string', 'max:255'], 'translations.*.url_override' => ['nullable', 'string', 'max:500']];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        if (isset($attributes['url']) && ! $this->safe->isSafeUrl((string) $attributes['url'])) {
            $this->fail('url', 'El enlace usa un protocolo no permitido.');
        }
        $translations = (array) ($attributes['translations'] ?? []);
        $locale = $this->localization->defaultLocale();
        if ($creating && trim((string) ($translations[$locale]['title'] ?? '')) === '') {
            $this->fail("translations.{$locale}.title", 'El título del idioma principal es obligatorio.');
        }
        $attributes['translations'] = array_intersect_key($translations, array_fill_keys($this->localization->activeLocales(), true));
        foreach ($attributes['translations'] as $values) {
            if (is_array($values) && ! empty($values['url_override']) && ! $this->safe->isSafeUrl((string) $values['url_override'])) {
                $this->fail('translations', 'El enlace usa un protocolo no permitido.');
            }
        }
        if (! empty($attributes['parent_id'])) {
            $parent = MenuItem::find((int) $attributes['parent_id']);
            $menuId = (int) ($attributes['menu_id'] ?? $model->getAttributes()['menu_id'] ?? 0);
            $seen = [(int) $model->getKey()];
            while ($parent instanceof MenuItem) {
                if ($parent->menu_id !== $menuId || in_array((int) $parent->getKey(), $seen, true)) {
                    $this->fail('parent_id', 'Selecciona un padre del mismo menú sin ciclos.');
                }
                $seen[] = (int) $parent->getKey();
                $parent = $parent->parent;
            }
        }

        return $creating ? $attributes + ['order' => 0, 'is_active' => true, 'target_blank' => false] : $attributes;
    }

    public function saved(Model $model, array $attributes, bool $creating): void
    {
        if (! $model instanceof MenuItem) {
            throw new \LogicException('Expected a menu item.');
        }
        foreach ((array) ($attributes['translations'] ?? []) as $locale => $values) {
            if (is_array($values) && trim((string) ($values['title'] ?? '')) !== '') {
                $model->translations()->updateOrCreate(['locale' => $locale], ['title' => trim((string) $values['title']), 'url_override' => $values['url_override'] ?? null]);
            }
        }
    }

    public function deleting(Model $model): void
    {
        if (! $model instanceof MenuItem) {
            throw new \LogicException('Expected a menu item.');
        }
        $model->translations()->delete();
    }
}

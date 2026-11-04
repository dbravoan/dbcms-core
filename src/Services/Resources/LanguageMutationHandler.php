<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\SearchContextRebuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class LanguageMutationHandler extends AbstractResourceMutationHandler
{
    public function rules(Model $model, bool $creating): array
    {
        return ['code' => ['required', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($model->getKey())], 'name' => ['required', 'string', 'max:255'], 'native_name' => ['required', 'string', 'max:255'], 'flag' => ['nullable', 'string', 'max:16'], 'direction' => ['nullable', 'in:ltr,rtl'], 'is_default' => ['boolean'], 'is_active' => ['boolean']];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        if (isset($attributes['code'])) {
            $attributes['code'] = strtolower((string) $attributes['code']);
        }
        $default = (bool) ($model->getAttributes()['is_default'] ?? false);
        if ($default && array_key_exists('is_active', $attributes) && ! filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            $this->fail('error', 'No puedes desactivar el idioma principal.');
        }
        if ($default && array_key_exists('is_default', $attributes) && ! filter_var($attributes['is_default'], FILTER_VALIDATE_BOOLEAN)) {
            $this->fail('error', 'No puedes quitar el idioma principal sin seleccionar otro.');
        }
        if ($model->exists && isset($attributes['code']) && $attributes['code'] !== $model->getAttribute('code') && Market::where('default_locale', $model->getAttribute('code'))->exists()) {
            $this->fail('code', 'Este idioma es el locale por defecto de un mercado; cámbialo antes de renombrarlo.');
        }
        if (! empty($attributes['is_default'])) {
            Language::query()->lockForUpdate()->get();
        }

        return $creating ? $attributes + ['is_default' => false, 'is_active' => true, 'direction' => 'ltr'] : $attributes;
    }

    public function saved(Model $model, array $attributes, bool $creating): void
    {
        if (! empty($attributes['is_default'])) {
            Language::whereKeyNot($model->getKey())->update(['is_default' => false]);
        }
        app(SearchContextRebuilder::class)->afterCommit();
    }

    public function deleting(Model $model): void
    {
        if (! $model instanceof Language) {
            throw new \LogicException('Expected a language.');
        }
        if ((bool) ($model->getAttributes()['is_default'] ?? false)) {
            $this->fail('error', 'No puedes eliminar el idioma principal.');
        }
        if (Market::query()->where('default_locale', $model->code)->exists()) {
            $this->fail('error', 'Este idioma es el locale por defecto de un mercado; cámbialo antes de eliminarlo.');
        }
        $model->markets()->detach();
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Localization\LocalizationUsage;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\SearchContextRebuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class MarketMutationHandler extends AbstractResourceMutationHandler
{
    public function __construct(private readonly LocalizationUsage $usage = new LocalizationUsage) {}

    public function rules(Model $model, bool $creating): array
    {
        return ['code' => ['required', 'string', 'max:10', Rule::unique('markets', 'code')->ignore($model->getKey())], 'name' => ['required', 'string', 'max:255'], 'flag' => ['nullable', 'string', 'max:16'], 'currency' => ['required', 'string', 'max:10'], 'default_locale' => ['required', 'string', 'max:10'], 'is_default' => ['boolean'], 'is_active' => ['boolean'], 'fallback_market_id' => ['nullable', 'integer', 'exists:markets,id', Rule::notIn([$model->getKey()])], 'language_ids' => ['nullable', 'array'], 'language_ids.*' => ['integer', 'exists:languages,id']];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        foreach (['code', 'default_locale'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = strtolower((string) $attributes[$field]);
            }
        }
        if (isset($attributes['currency'])) {
            $attributes['currency'] = strtoupper((string) $attributes['currency']);
        }
        $default = (bool) ($model->getAttributes()['is_default'] ?? false);
        if ($default && array_key_exists('is_active', $attributes) && ! filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            $this->fail('error', 'No puedes desactivar el mercado principal.');
        }
        if ($default && array_key_exists('is_default', $attributes) && ! filter_var($attributes['is_default'], FILTER_VALIDATE_BOOLEAN)) {
            $this->fail('error', 'No puedes quitar el mercado principal sin seleccionar otro.');
        }
        if (! empty($attributes['is_default'])) {
            Market::query()->lockForUpdate()->get();
        }

        return $creating ? $attributes + ['is_default' => false, 'is_active' => true] : $attributes;
    }

    public function saved(Model $model, array $attributes, bool $creating): void
    {
        if (! $model instanceof Market) {
            throw new \LogicException('Expected a market.');
        }
        if (! empty($attributes['is_default'])) {
            Market::whereKeyNot($model->getKey())->update(['is_default' => false]);
        }
        if (array_key_exists('language_ids', $attributes)) {
            $model->languages()->sync((array) $attributes['language_ids']);
        }
        app(SearchContextRebuilder::class)->afterCommit();
    }

    public function deleting(Model $model): void
    {
        if (! $model instanceof Market) {
            throw new \LogicException('Expected a market.');
        }
        if ((bool) ($model->getAttributes()['is_default'] ?? false)) {
            $this->fail('error', 'No puedes eliminar el mercado principal.');
        }
        $usage = $this->usage->marketUsage($model);
        if ($usage !== []) {
            $detail = collect($usage)->map(static fn (int $count, string $table): string => $table.' ('.$count.')')->implode(', ');
            $this->fail('error', 'Este mercado tiene contenido asociado y no se puede eliminar: '.$detail);
        }
        $model->languages()->detach();
    }
}

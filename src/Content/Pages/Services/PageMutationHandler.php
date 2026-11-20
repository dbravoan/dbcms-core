<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PageMutationHandler implements EntityMutationHandlerInterface
{
    public function __construct(private readonly PageViewRegistry $views) {}

    public function prepare(Model $entity, array $args, bool $creating): array
    {
        if (! $entity instanceof Page) {
            throw new \InvalidArgumentException('Page mutations require a Page.');
        }
        $attributes = (array) ($args['attributes'] ?? []);
        $translations = (array) ($args['translations'] ?? []);
        if ($creating) {
            $attributes += ['template' => 'default', 'layout' => 'app'];
        }
        $currentTemplate = $entity->getAttribute('template') ?? 'default';
        $template = array_key_exists('template', $attributes) ? $attributes['template'] : $currentTemplate;
        if (! is_string($template) || strlen($template) > 100) {
            throw ValidationException::withMessages(['template' => 'Select a valid page template.']);
        }
        $this->views->resolve($template);
        if (PageViewRegistry::isBlade($template) && (array_key_exists('sections_json', $args) || array_key_exists('sections', $args))) {
            throw ValidationException::withMessages(['sections_json' => 'Switch to a section template before editing its preserved sections.']);
        }
        if (array_key_exists('template_data', $attributes)) {
            throw ValidationException::withMessages(['template_data' => 'Escribe los campos Blade en translations.<idioma>.template_data.']);
        }
        if ($entity->exists && $template !== $currentTemplate) {
            $entity->translations()->whereNotNull('template_data')->update(['template_data' => null]);
        }
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }
            if (array_key_exists('template_data', $values)) {
                if (! is_string($locale) || $locale === '' || Language::where('code', $locale)->value('code') !== $locale) {
                    throw ValidationException::withMessages(['translations' => 'Usa un idioma configurado para los campos de plantilla.']);
                }
                $translation = $entity->exists
                    ? $entity->translations()->whereNull('market_id')->where('locale', $locale)->first()
                    : null;
                $patch = $this->views->patchValues($template, $translation?->template_data, $values['template_data'], $locale);
                if ($patch !== [] && $translation === null
                    && (! is_string($values['title'] ?? null) || trim($values['title']) === '')) {
                    throw ValidationException::withMessages(['translations.'.$locale.'.title' => 'Indica un título al añadir campos de plantilla a un idioma nuevo.']);
                }
                if ($translation !== null || $patch !== []) {
                    $values['template_data'] = $patch === [] ? null : $patch;
                } else {
                    unset($values['template_data']);
                }
            }
            if ($creating || array_key_exists('title', $values)) {
                $values['meta_title'] ??= $values['title'] ?? '';
            }
            if ($creating) {
                $values['schema_type'] ??= 'WebPage';
            }
            $translations[$locale] = $values;
        }
        if (($attributes['is_published'] ?? $attributes['publish'] ?? false) && ! isset($attributes['published_at'])) {
            $attributes['published_at'] = $entity->getAttribute('published_at') ?? now();
        }

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }
}

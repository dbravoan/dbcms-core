<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PageMutationHandler implements EntityMutationHandlerInterface
{
    public function __construct(private readonly PageViewRegistry $views) {}

    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        $translations = (array) ($args['translations'] ?? []);
        if ($creating) {
            $attributes += ['template' => 'default', 'layout' => 'app'];
        }
        $template = array_key_exists('template', $attributes) ? $attributes['template'] : ($entity->getAttribute('template') ?? 'default');
        if (! is_string($template) || strlen($template) > 100) {
            throw ValidationException::withMessages(['template' => 'Select a valid page template.']);
        }
        $this->views->resolve($template);
        if (PageViewRegistry::isBlade($template) && (array_key_exists('sections_json', $args) || array_key_exists('sections', $args))) {
            throw ValidationException::withMessages(['sections_json' => 'Switch to a section template before editing its preserved sections.']);
        }
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
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

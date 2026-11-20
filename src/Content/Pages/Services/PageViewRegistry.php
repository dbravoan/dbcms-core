<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Services\Content\RichTextContent;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Only developer-registered names can select executable Blade templates. */
final class PageViewRegistry
{
    private const MAX_LENGTH = 16000;

    /** @var array<string,string> */
    private array $core = [];

    public function __construct(private readonly Repository $config, private readonly Factory $views, private readonly RichTextContent $richText) {}

    public function registerCore(string $key, string $view): void
    {
        $this->assertEntry($key, $view);
        if (isset($this->core[$key])) {
            throw new \LogicException('Duplicate core page view: '.$key);
        }
        $this->core[$key] = $view;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        return $this->core + array_map(static fn (array $entry): string => $entry['view'], $this->projectDefinitions());
    }

    /** @return array<string, array<string, array{type: string, label: string, default: string|array{format: string, content: string}, translatable: bool, max_length: int, formats?: list<string>}>> */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->projectDefinitions() as $key => $entry) {
            if ($this->views->exists($entry['view'])) {
                $catalog['blade:'.$key] = $this->describeFields($entry['fields']);
            }
        }

        return $catalog;
    }

    /** @return array<string, HtmlString|string> */
    public function publicValues(Page $page, PageTranslation $translation): array
    {
        $global = $translation->market_id === null ? null : $page->translations->whereNull('market_id')->firstWhere('locale', $translation->locale);
        $values = [];
        foreach ($this->fields((string) $page->template) as $key => $field) {
            $source = $field['default'];
            foreach ([$global?->template_data[$key] ?? null, $translation->template_data[$key] ?? null] as $candidate) {
                $source = $this->normalizeValue($field['type'], $candidate) ?? $source;
            }
            $values[$key] = is_array($source) ? new HtmlString($this->richText->render($source)) : $source;
        }

        return $values;
    }

    /**
     * @param  array<string, string|array{format: string, content: string}>|null  $stored
     * @return array<string, string|array{format: string, content: string}>
     */
    public function patchValues(string $template, ?array $stored, mixed $input, string $locale): array
    {
        $fields = $this->fields($template);
        $path = 'translations.'.$locale.'.template_data';
        if ($fields === [] || ! is_array($input) || array_diff_key($input, $fields) !== []) {
            throw ValidationException::withMessages([$path => 'Usa solo los campos declarados en la plantilla Blade.']);
        }

        $values = array_intersect_key($stored ?? [], $fields);
        foreach ($input as $key => $value) {
            if ($value === null) {
                unset($values[$key]);

                continue;
            }
            $field = $fields[$key];
            $source = $this->normalizeValue($field['type'], $value);
            if ($source === null) {
                throw ValidationException::withMessages([$path.'.'.$key => __('admin.entity_form.invalid_template_value', ['max' => $field['max_length']])]);
            }
            if ((is_array($source) ? $source['content'] : $source) === '') {
                unset($values[$key]);
            } else {
                $values[$key] = $source;
            }
        }

        return $values;
    }

    /** @return array<string, array{view: string, fields: array<array-key, mixed>}> */
    private function projectDefinitions(): array
    {
        $project = $this->config->get('cms.page_views', []);
        if (! is_array($project)) {
            throw new \LogicException('cms.page_views must map keys to Blade view definitions.');
        }
        $registered = [];
        foreach ($project as $key => $entry) {
            $view = is_array($entry) ? ($entry['view'] ?? null) : $entry;
            $fields = is_array($entry) ? ($entry['fields'] ?? []) : [];
            if (! is_string($key) || ! is_string($view) || ! is_array($fields)) {
                throw new \LogicException('A page view definition requires a string key, a view name and optional field definitions.');
            }
            $this->assertEntry($key, $view);
            if (isset($this->core[$key])) {
                throw new \LogicException('A project cannot replace a reserved page view: '.$key);
            }
            $registered[$key] = ['view' => $view, 'fields' => $fields];
        }

        return $registered;
    }

    public static function isBlade(string $template): bool
    {
        return str_starts_with($template, 'blade:');
    }

    /** @return view-string|null */
    public function resolve(string $template): ?string
    {
        if (! self::isBlade($template)) {
            return null;
        }
        $key = substr($template, 6);
        $view = $this->all()[$key] ?? null;
        if ($view === null || ! $this->views->exists($view)) {
            throw ValidationException::withMessages(['template' => 'This registered page view is unavailable: '.$key]);
        }

        return $view;
    }

    /** @return array<string, array{type: string, label: string, default: string|array{format: string, content: string}, translatable: bool, max_length: int, formats?: list<string>}> */
    private function fields(string $template): array
    {
        if (! self::isBlade($template)) {
            return [];
        }

        return $this->describeFields($this->projectDefinitions()[substr($template, 6)]['fields'] ?? []);
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<string, array{type: string, label: string, default: string|array{format: string, content: string}, translatable: bool, max_length: int, formats?: list<string>}>
     */
    private function describeFields(array $fields): array
    {
        $descriptions = [];
        foreach ($fields as $key => $definition) {
            $label = $definition['label'] ?? $key;
            $type = $definition['type'] ?? 'text';
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) || ! is_array($definition) || ! is_string($label)
                || ! in_array($type, ['text', 'rich_text'], true) || ($definition['translatable'] ?? true) !== true) {
                throw new \LogicException('Invalid page template field: '.$key);
            }
            $default = $this->normalizeValue($type, $definition['default'] ?? ($type === 'text' ? '' : ['format' => 'html', 'content' => '']));
            if ($default === null) {
                throw new \LogicException('Invalid page template field default: '.$key);
            }
            $descriptions[$key] = [
                'type' => $type,
                'label' => $label,
                'default' => $default,
                'translatable' => true,
                'max_length' => $type === 'text' ? 255 : self::MAX_LENGTH,
            ];
            if ($type === 'rich_text') {
                $descriptions[$key]['formats'] = RichTextContent::FORMATS;
            }
        }

        return $descriptions;
    }

    /** @return string|array{format: string, content: string}|null */
    private function normalizeValue(string $type, mixed $value): string|array|null
    {
        if ($type === 'text') {
            return is_string($value) && mb_check_encoding($value, 'UTF-8')
                && mb_strlen($value, 'UTF-8') <= 255 && ! preg_match('/[\x00-\x1f\x7f]|\R/u', $value) ? $value : null;
        }

        $source = $this->richText->source($value, self::MAX_LENGTH);
        if ($source !== null && $source['format'] === 'html') {
            $source['content'] = $this->richText->render($source);
        }

        return $this->richText->source($source, self::MAX_LENGTH);
    }

    private function assertEntry(string $key, string $view): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,93}$/D', $key) || ! preg_match('/^[A-Za-z0-9_-]+(?:::[A-Za-z0-9_-]+)?(?:\.[A-Za-z0-9_-]+)*$/D', $view)) {
            throw new \LogicException('Invalid registered page key or Blade view name.');
        }
    }
}

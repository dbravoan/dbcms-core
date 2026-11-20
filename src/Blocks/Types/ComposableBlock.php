<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\BlockPresentation;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\BlockStorageDisclosure;
use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Content\SafeContentRenderer;
use DbCms\Core\Services\Media\MarkdownRendererService;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Support\Str;

/** Shared presentation boundary for native primitives. */
abstract class ComposableBlock extends AbstractBlockType implements ComposableBlockInterface
{
    protected const TYPE = '';

    protected const NAME = '';

    protected const ICON = 'fa-solid fa-cube';

    protected const CATEGORY = 'content';

    /** @var array<string, mixed> */
    protected const DEFAULTS = [];

    /**
     * Spanish example copy for «Con datos de ejemplo». Only overrides DEFAULTS
     * keys, so empty inserts and stored payloads keep rendering exactly as before.
     *
     * @var array<string, mixed>
     */
    protected const EXAMPLE = [];

    /** @var array<string, mixed> English example copy for non-Spanish content locales. */
    protected const EXAMPLE_EN = [];

    /** @var list<string> */
    protected const TRANSLATABLE = [];

    public function getType(): string
    {
        return static::TYPE;
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public function getIcon(): string
    {
        return static::ICON;
    }

    public function getCategory(): string
    {
        return static::CATEGORY;
    }

    public function getDescription(): string
    {
        return static::NAME.'. Pieza independiente que admite la apariencia compartida del editor.';
    }

    public function getDefaultPayload(): array
    {
        return array_replace(static::DEFAULTS, static::EXAMPLE);
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es') || static::EXAMPLE_EN === []) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), static::EXAMPLE_EN);
    }

    public function getTranslationPaths(): array
    {
        return static::TRANSLATABLE;
    }

    /** @param array<string, array<string, mixed>> $schema
     * @return array<string, array<string, mixed>>
     */
    protected static function withDefaults(array $schema): array
    {
        foreach ($schema as $key => &$field) {
            if (array_key_exists($key, static::DEFAULTS)) {
                $field['default'] = static::DEFAULTS[$key];
            }
        }
        unset($field);

        return $schema;
    }

    /** @return array<string, mixed> */
    protected static function text(string $label, int $max = 255, string $type = 'text'): array
    {
        return ['type' => $type, 'label' => $label, 'rules' => ['nullable', 'string', 'max:'.$max]];
    }

    /** @param array<string, string> $options
     * @return array<string, mixed>
     */
    protected static function select(string $label, array $options): array
    {
        return ['type' => 'select', 'label' => $label, 'options' => $options, 'rules' => ['nullable', 'string', 'in:'.implode(',', array_keys($options))]];
    }

    /** @return array<string, mixed> */
    protected static function number(string $label, int|float $min, int|float $max, bool $integer = false): array
    {
        return ['type' => $integer ? 'integer' : 'number', 'label' => $label, 'min' => $min, 'max' => $max, 'rules' => ['nullable', $integer ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max]];
    }

    /** @return array<string, mixed> */
    protected static function boolean(string $label): array
    {
        return ['type' => 'boolean', 'label' => $label, 'rules' => ['nullable', 'boolean']];
    }

    /** @return array<string, mixed> */
    protected static function media(string $label, string $kind = 'image'): array
    {
        return self::text($label, 2048, 'media') + ['media_kind' => $kind];
    }

    /** @return array<string, mixed> */
    protected static function childrenField(): array
    {
        return ['type' => 'blocks', 'label' => 'Contenido', 'rules' => ['nullable', 'array', 'max:100']];
    }

    /** @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    protected static function items(array $fields, string $label = 'Elementos'): array
    {
        return ['type' => 'repeater', 'label' => $label, 'fields' => $fields, 'rules' => ['nullable', 'array', 'max:100']];
    }

    /** @return array<string, mixed> */
    protected static function externalService(): array
    {
        return self::select('Servicio externo declarado', ['' => 'Sin servicio externo'] + array_map(static fn (array $service): string => $service['provider'], app(ExternalContentPolicy::class)->services()));
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $schema = $this->getEditorSchema();
        $values = $this->safeValues(array_replace(static::DEFAULTS, $payload), $schema, $market, $locale);
        $items = [];
        $itemSchema = ($schema['items']['type'] ?? null) === 'repeater' ? (array) ($schema['items']['fields'] ?? []) : [];
        foreach ($itemSchema === [] ? [] : (array) ($values['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($itemSchema['children']['type'] ?? null) === 'blocks') {
                $item['content_html'] = app(BlockRenderer::class)->children($this->childNodes($item['children'] ?? []), $market, $locale);
            } elseif (isset($itemSchema['text'])) {
                $item['content_html'] = $this->markdownHtml((string) ($item['text'] ?? ''));
            }
            if (static::TYPE === 'accordion') {
                $item['icon_text'] = ($values['icon_style'] ?? 'plus_minus') === 'chevron' ? (! empty($item['open']) ? '⌄' : '›') : (! empty($item['open']) ? '−' : '+');
            }
            if (static::TYPE === 'hotspots') {
                $item['x'] = max(0, min(100, (float) ($item['x'] ?? 50)));
                $item['y'] = max(0, min(100, (float) ($item['y'] ?? 50)));
            }
            $items[] = $item;
        }
        $values['items'] = $items;
        $fallback = 'basic::blocks.'.static::TYPE;
        if (! view()->exists($fallback)) {
            throw new \LogicException('The basic block owner view is missing: '.$fallback);
        }
        $ownerView = $fallback;
        $view = app(FrontViewResolver::class)->resolve('basic.blocks.'.static::TYPE, $ownerView);

        return view($view, $this->presentation($values, $schema, $market, $locale))->render();
    }

    /** @param array<string, mixed> $values
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    private function presentation(array $values, array $schema, ?Market $market, ?string $locale): array
    {
        $data = ['p' => $values, 'uid' => 'cms-'.static::TYPE.'-'.Str::uuid(), 'locale' => $locale ?? app()->getLocale()];
        $data['completionHtml'] = ($schema['completion_children']['type'] ?? null) === 'blocks' ? app(BlockRenderer::class)->children($this->childNodes($values['completion_children'] ?? []), $market, $locale) : '';
        $data['accordionHeadingTag'] = in_array($values['heading_level'] ?? '', ['h2', 'h3', 'h4', 'h5', 'h6'], true) ? $values['heading_level'] : 'h3';
        $data['childrenHtml'] = ($schema['children']['type'] ?? null) === 'blocks'
            ? app(BlockRenderer::class)->children($this->childNodes($values['children'] ?? []), $market, $locale)
            : '';
        $data['alertHtml'] = static::TYPE === 'alert' ? $this->markdownHtml((string) ($values['content'] ?? '')) : '';
        $data['headingTag'] = in_array($values['level'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $values['level'] : 'h2';
        $data['listTag'] = ($values['ordered'] ?? false) ? 'ol' : 'ul';
        $data['containerTag'] = in_array($values['tag'] ?? '', ['div', 'section', 'article', 'aside', 'nav'], true) ? $values['tag'] : 'div';
        $data['layoutClass'] = match ($values['layout'] ?? '') {
            'row' => 'cms-composition-row', 'grid' => 'cms-composition-grid', default => 'cms-composition-stack',
        };
        $data['gap'] = max(0, min(96, (int) ($values['gap'] ?? 24)));
        $data['columns'] = max(1, min(6, (int) ($values['grid_columns'] ?? 3)));
        $data['dimension'] = max(0, min(400, (int) ($values['height'] ?? 32)));
        $data['ratingMax'] = max(1, min(10, (int) ($values['maximum'] ?? 5)));
        $data['ratingValue'] = max(0, min($data['ratingMax'], (float) ($values['value'] ?? 0)));
        $minimum = (float) ($values['minimum'] ?? 0);
        $maximum = max($minimum + 0.001, (float) ($values['maximum'] ?? 100));
        $data['progressValue'] = max($minimum, min($maximum, (float) ($values['value'] ?? 0)));
        $data['progressMinimum'] = $minimum;
        $data['progressMaximum'] = $maximum;
        $data['progressPercent'] = ($data['progressValue'] - $minimum) / ($maximum - $minimum) * 100;
        $data['externalHtml'] = static::TYPE === 'map' ? $this->map($values) : '';
        $data['playlistHtml'] = static::TYPE === 'playlist' ? $this->playlist($values, $data['uid']) : '';
        $deadline = (string) ($values['deadline'] ?? '');
        $data['countdownStorageEnabled'] = static::TYPE === 'countdown' && app(BlockStorageDisclosure::class)->available() && ($values['storage_purpose'] ?? 'preferences') === config('cms.blocks.countdown_storage.purpose', 'preferences');
        $data['countdownStorageDays'] = app(BlockStorageDisclosure::class)->maxDays();
        $data['countdownRevision'] = hash('sha256', json_encode(['definition' => array_intersect_key($values, array_flip(['mode', 'duration', 'deadline', 'persistence', 'storage_purpose'])), 'max_days' => $data['countdownStorageDays']], JSON_THROW_ON_ERROR));
        $data['deadline'] = preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d)?(?:Z|[+-]\d\d:\d\d)$/D', $deadline) === 1 ? $deadline : '';
        $data['overlayAlias'] = preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D', (string) ($values['overlay_id'] ?? '')) === 1 ? $values['overlay_id'] : '';
        $data['overlayId'] = $data['uid'];
        $data['iconClass'] = preg_match('/^fa-(?:solid|regular|brands) fa-[a-z0-9-]+$/D', (string) ($values['icon'] ?? '')) === 1 ? $values['icon'] : '';
        $data['imageFit'] = ($values['fit'] ?? '') === 'contain' ? 'contain' : 'cover';
        $data['imageWidth'] = max(0, min(16000, (int) ($values['width'] ?? 0)));
        $data['imageHeight'] = max(0, min(16000, (int) ($values['height'] ?? 0)));
        $data['buttonVariant'] = in_array($values['variant'] ?? '', ['primary', 'secondary', 'ghost'], true) ? $values['variant'] : 'primary';
        $data['alertTone'] = in_array($values['tone'] ?? '', ['info', 'success', 'warning', 'danger'], true) ? $values['tone'] : 'info';
        $data['drawerSide'] = in_array($values['side'] ?? '', ['left', 'right', 'top', 'bottom'], true) ? $values['side'] : 'right';
        $data['drawerMediaHtml'] = static::TYPE === 'drawer' ? app(BlockPresentation::class)->mediaMarkup($values) : '';
        $data['drawerMotionAttributes'] = static::TYPE === 'drawer' ? app(BlockPresentation::class)->motionAttributes($values) : [];
        $data['buttonAction'] = in_array($values['action'] ?? '', ['open_overlay', 'close_overlay'], true) ? $values['action'] : 'link';
        $data['buttonOverlay'] = preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D', (string) ($values['overlay_id'] ?? '')) === 1 ? $values['overlay_id'] : '';
        if (($values['mode'] ?? '') === 'reading' && static::TYPE === 'progress') {
            $data['progressValue'] = 0;
            $data['progressMinimum'] = 0;
            $data['progressMaximum'] = 100;
            $data['progressPercent'] = 0;
        }
        $data['counterDecimals'] = max(0, min(6, (int) ($values['decimals'] ?? 0)));
        $data['counterValue'] = number_format((float) ($values['value'] ?? 0), $data['counterDecimals'], '.', ',');

        return $data;
    }

    private function markdownHtml(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        return app(MarkdownRendererService::class)->render($source);
    }

    /** @return list<array<string, mixed>> */
    private function childNodes(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $nodes = [];
        foreach ($value as $node) {
            if (! is_array($node)) {
                continue;
            }
            $fields = [];
            foreach ($node as $key => $field) {
                if (is_string($key)) {
                    $fields[$key] = $field;
                }
            }
            $nodes[] = $fields;
        }

        return $nodes;
    }

    /** @param array<string, mixed> $values
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    private function safeValues(array $values, array $schema, ?Market $market, ?string $locale): array
    {
        foreach ($schema as $key => $field) {
            if (in_array($field['type'], ['url', 'media'], true)) {
                $value = is_string($values[$key] ?? null) ? $values[$key] : '';
                if (! app(SafeContentRenderer::class)->isSafeUrl($value, ($field['type'] === 'media' || ($field['asset'] ?? false)))) {
                    $value = '';
                }
                if ($field['type'] === 'url' && ! ($field['asset'] ?? false) && str_starts_with($value, '/') && ! str_starts_with($value, '//') && app()->bound(LocalizationServiceInterface::class)) {
                    $value = app(LocalizationServiceInterface::class)->url($value, $market, $locale);
                }
                $values[$key] = $value;
            }
            if ($field['type'] === 'repeater') {
                $values[$key] = array_map(fn (array $item): array => $this->safeValues($item, (array) $field['fields'], $market, $locale), array_values(array_filter((array) ($values[$key] ?? []), is_array(...))));
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    private function map(array $values): string
    {
        $policy = app(ExternalContentPolicy::class);
        $service = (string) ($values['external_service'] ?? '');
        $url = (string) ($values['embed_url'] ?? '');
        if (! $policy->permits($service, $url)) {
            return $policy->render('', '');
        }
        $html = '<iframe src="'.e($url).'" title="'.e((string) ($values['title'] ?? __('Mapa'))).'" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin" class="cms-map-frame"></iframe>';

        return $policy->render($html, $service);
    }

    /** @param array<string, mixed> $values */
    private function playlist(array $values, string $uid): string
    {
        $policy = app(ExternalContentPolicy::class);
        $service = (string) ($values['external_service'] ?? '');
        $tag = ($values['kind'] ?? '') === 'video' ? 'video' : 'audio';
        $assetOrigins = array_merge([], ...array_column($policy->assetServices(), 'origins'));
        $native = fn (string $url): bool => $this->isLocal($url) || array_any($assetOrigins, static fn (string $origin): bool => $url === $origin || str_starts_with($url, $origin.'/'));
        $tracks = [];
        foreach ((array) $values['items'] as $item) {
            $sourceKey = $tag === 'video' ? 'video_url' : 'media_url';
            if (! is_array($item) || ! is_string($item[$sourceKey] ?? null) || $item[$sourceKey] === '') {
                continue;
            }
            $url = $item[$sourceKey];
            if (! $native($url) && ! $policy->permits($service, $url)) {
                continue;
            }
            $poster = (string) ($item['poster_url'] ?? '');
            $subtitle = (string) ($item['subtitle_url'] ?? '');
            if ($poster !== '' && ! $native($poster) && ! $policy->permits($service, $poster)) {
                $poster = '';
            }
            if ($subtitle !== '' && ! $native($subtitle) && ! $policy->permits($service, $subtitle)) {
                $subtitle = '';
            }
            $language = (string) ($item['subtitle_language'] ?? app()->getLocale());
            if (preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/D', $language) !== 1) {
                $language = app()->getLocale();
            }
            $title = trim((string) ($item['title'] ?? ''));
            $subtitleLabel = trim((string) ($item['subtitle_label'] ?? ''));
            $tracks[] = ['url' => $url, 'title' => $title !== '' ? $title : (string) __('Pista').' '.(count($tracks) + 1), 'poster' => $poster, 'subtitle' => $subtitle,
                'language' => $language, 'subtitle_label' => $subtitleLabel !== '' ? $subtitleLabel : $language,
                'external' => ! $native($url) || ($poster !== '' && ! $native($poster)) || ($subtitle !== '' && ! $native($subtitle))];
        }
        if ($tracks === []) {
            return '';
        }
        $view = app(FrontViewResolver::class)->resolve('basic.blocks.playlist-player', 'basic::blocks.playlist-player');
        $html = view($view, ['tracks' => $tracks, 'tracksJson' => json_encode($tracks, JSON_THROW_ON_ERROR), 'tag' => $tag, 'uid' => $uid, 'settings' => $values])->render();

        return in_array(true, array_column($tracks, 'external'), true) ? $policy->render($html, $service) : $html;
    }

    private function isLocal(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) {
            return true;
        }
        $site = (string) config('app.url');

        return $host === parse_url($site, PHP_URL_HOST)
            && parse_url($url, PHP_URL_SCHEME) === parse_url($site, PHP_URL_SCHEME)
            && parse_url($url, PHP_URL_PORT) === parse_url($site, PHP_URL_PORT);
    }
}

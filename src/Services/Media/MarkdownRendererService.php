<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Services\Content\SafeContentRenderer;

class MarkdownRendererService
{
    public function __construct(private readonly SafeContentRenderer $safe = new SafeContentRenderer) {}

    /**
     * Compiles Markdown prose into sanitized HTML and resolves embedded widget shortcodes.
     */
    public function render(string $markdownContent): string
    {
        $html = $this->safe->markdown($markdownContent);

        // 2. Resolve widget shortcodes: [widget:widget_posts limit="3"]
        return $this->resolveShortcodes($html);
    }

    /**
     * Renders pure HTML content directly and resolves embedded widget shortcodes.
     */
    public function renderHtml(string $htmlContent): string
    {
        return $this->resolveShortcodes($this->safe->html($htmlContent));
    }

    /**
     * Resolves widget shortcodes inside HTML markup.
     */
    public function resolveShortcodes(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[widget:([a-zA-Z0-9_\-\.]+)(.*?)\]/',
            function (array $matches): string {
                $widgetKey = $matches[1];
                $attributesRaw = $matches[2];
                $parameters = $this->parseShortcodeAttributes($attributesRaw);

                return app(BlockRegistryInterface::class)->render($widgetKey, $parameters);
            },
            $html
        );
    }

    /**
     * Parses shortcode key="val" attributes.
     *
     * @return array<string, mixed>
     */
    protected function parseShortcodeAttributes(string $raw): array
    {
        $attributes = [];
        preg_match_all('/(\w+)=["\']([^"\']+)["\']/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }
}

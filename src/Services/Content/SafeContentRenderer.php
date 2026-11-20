<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DbCms\Core\Blocks\BlockStyle;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/** One rendering boundary for untrusted editorial HTML and Markdown. */
final class SafeContentRenderer
{
    /** @var list<string> */
    private const TAGS = ['p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'figure', 'figcaption', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'small', 'sub', 'sup', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'hr', 'br', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'details', 'summary'];

    /** @var list<string> */
    private const EDITOR_MEDIA = ['video', 'audio'];

    /** @var list<string> */
    private const DISCARD = ['script', 'style', 'svg', 'math', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'xmp', 'plaintext', 'form', 'input', 'button', 'select', 'option', 'link', 'meta', 'base'];

    public function markdown(string $content): string
    {
        return $this->html(Str::markdown($content, ['allow_unsafe_links' => false, 'max_nesting_level' => 30]));
    }

    public function html(string $content): string
    {
        return $this->sanitize($content, visual: false);
    }

    public function richText(string $content, string $format): string
    {
        if ($format === 'markdown') {
            $content = Str::markdown($content, ['allow_unsafe_links' => false, 'max_nesting_level' => 30]);
        }

        return $this->sanitize($content, visual: true);
    }

    /** @return array<string, array<string|int, string>> */
    public static function typography(): array
    {
        $properties = BlockStyle::properties();

        return [
            'text-align' => $properties['text_align']['options'],
            'font-family' => $properties['font_family']['options'],
            'font-weight' => $properties['font_weight']['options'],
        ];
    }

    private function sanitize(string $content, bool $visual): string
    {
        if ($content === '') {
            return '';
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$content.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            if ($body === null) {
                return '';
            }
            $this->cleanChildren($body, $visual);
            $html = '';
            foreach ($body->childNodes as $node) {
                $html .= $document->saveHTML($node);
            }

            return $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function cleanChildren(DOMNode $parent, bool $visual): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($node);
                }

                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DISCARD, true)) {
                $parent->removeChild($node);

                continue;
            }
            $this->cleanChildren($node, $visual);
            $media = $visual && in_array($tag, self::EDITOR_MEDIA, true);
            if (! $media && ! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                if ($visual && $name === 'style' && preg_match('/^(?:p|h[1-6])$/D', $tag)) {
                    $style = $this->paragraphStyle($attribute->nodeValue ?? '');
                    $style === '' ? $node->removeAttribute('style') : $node->setAttribute('style', $style);

                    continue;
                }
                $allowed = in_array($name, ['class', 'title', 'lang', 'dir'], true)
                    || ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true))
                    || ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height', 'loading'], true))
                    || (in_array($tag, ['td', 'th'], true) && in_array($name, ['colspan', 'rowspan', 'scope'], true))
                    || ($visual && $tag === 'ol' && $name === 'start' && ctype_digit($attribute->nodeValue ?? ''))
                    || ($media && in_array($name, ['src', 'aria-label'], true));
                if (! $allowed || (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->nodeValue ?? '', $name === 'src'))) {
                    $node->removeAttribute($attribute->nodeName);
                }
            }
            if ($media) {
                if (! $this->isRichMediaUrl($node->getAttribute('src'))) {
                    $parent->removeChild($node);

                    continue;
                }
                $node->setAttribute('controls', '');
                $node->setAttribute('preload', 'none');
                if ($tag === 'video') {
                    $node->setAttribute('playsinline', '');
                }
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function paragraphStyle(string $source): string
    {
        $options = self::typography();
        $styles = [];
        foreach (explode(';', $source) as $declaration) {
            [$name, $value] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');
            if ($value !== '' && isset($options[$name][$value])) {
                $styles[$name] = $name.': '.$value;
            }
        }

        $ordered = array_map(static fn (string $name): string => $styles[$name] ?? '', array_keys($options));

        return implode('; ', array_filter($ordered));
    }

    private function isRichMediaUrl(string $url): bool
    {
        if ($url === '' || ! $this->isSafeUrl($url, image: true) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (! isset($parts['host'])) {
            return ! isset($parts['scheme']) && str_starts_with($url, '/') && ! str_starts_with($url, '//');
        }
        $origin = ($parts['scheme'] ?? '').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $site = parse_url((string) config('app.url'));
        $local = ($site['scheme'] ?? '').'://'.($site['host'] ?? '').(isset($site['port']) ? ':'.$site['port'] : '');

        return $origin === $local || in_array($origin, app(ExternalContentPolicy::class)->assetOrigins(), true);
    }

    public function isSafeUrl(string $url, bool $image = false): bool
    {
        $normalized = (string) preg_replace('/[\x00-\x20\x7f]+/', '', $url);
        if (str_contains($normalized, '\\')) {
            return false;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalized, $matches) === 1) {
            return in_array(strtolower($matches[1]), $image ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'], true);
        }

        return ! str_contains(explode('/', $normalized, 2)[0], ':');
    }
}

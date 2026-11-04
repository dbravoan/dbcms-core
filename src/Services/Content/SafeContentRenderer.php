<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

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
    private const DISCARD = ['script', 'style', 'svg', 'math', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'xmp', 'plaintext', 'form', 'input', 'button', 'select', 'option', 'link', 'meta', 'base'];

    public function markdown(string $content): string
    {
        return $this->html(Str::markdown($content, ['allow_unsafe_links' => false, 'max_nesting_level' => 30]));
    }

    public function html(string $content): string
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
            $this->cleanChildren($body);
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

    private function cleanChildren(DOMNode $parent): void
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
            $this->cleanChildren($node);
            if (! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $allowed = in_array($name, ['class', 'title', 'lang', 'dir'], true)
                    || ($tag === 'a' && in_array($name, ['href', 'target', 'rel'], true))
                    || ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height', 'loading'], true))
                    || (in_array($tag, ['td', 'th'], true) && in_array($name, ['colspan', 'rowspan', 'scope'], true));
                if (! $allowed || (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->nodeValue ?? '', $name === 'src'))) {
                    $node->removeAttribute($attribute->nodeName);
                }
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
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

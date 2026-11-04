<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Sanitises uploaded SVG files before they are persisted.
 *
 * SVG is XML and can carry `<script>`, event handler attributes
 * (`onload=…`) and `javascript:` URLs. Served from the same origin, that is
 * stored XSS. This strips active content while keeping the drawing.
 */
final class SvgSanitizer
{
    /**
     * Elements that can execute or embed active content.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_ELEMENTS = [
        'script',
        'foreignObject',
        'iframe',
        'object',
        'embed',
        'audio',
        'video',
    ];

    public function sanitizeFile(string $path): void
    {
        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return;
        }

        @file_put_contents($path, $this->sanitize($contents));
    }

    public function sanitize(string $svg): string
    {
        if (trim($svg) === '') {
            return $svg;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        // LIBXML_NONET only: never expand external entities (XXE).
        $loaded = $dom->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $dom->documentElement === null) {
            return $this->stripFallback($svg);
        }

        $this->removeForbiddenElements($dom);
        $this->stripDangerousAttributes($dom);

        $sanitised = $dom->saveXML();

        return $sanitised === false ? $this->stripFallback($svg) : $sanitised;
    }

    private function removeForbiddenElements(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        foreach (self::FORBIDDEN_ELEMENTS as $element) {
            $nodes = $xpath->query('//*[local-name()="'.$element.'"]');

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                if ($node instanceof \DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }
    }

    private function stripDangerousAttributes(DOMDocument $dom): void
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim((string) $attribute->nodeValue);

                if (str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->nodeName);

                    continue;
                }

                if ($name === 'href' || $name === 'src' || $name === 'xlink:href') {
                    if (preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $value) === 1) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                }
            }
        }
    }

    /**
     * Crude regex fallback for input that is not parseable as XML.
     */
    private function stripFallback(string $svg): string
    {
        $svg = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = (string) preg_replace('#<(iframe|object|embed|foreignObject)\b[^>]*>.*?</\1>#is', '', $svg);
        $svg = (string) preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg);
        $svg = (string) preg_replace('#(href|src)\s*=\s*("|\')?\s*(javascript|vbscript|data):#i', '$1=$2#', $svg);

        return $svg;
    }
}

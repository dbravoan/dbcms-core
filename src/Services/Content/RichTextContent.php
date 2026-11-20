<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Content;

use DOMDocument;
use DOMElement;
use DOMText;
use Illuminate\Validation\ValidationException;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

final class RichTextContent
{
    public const FORMATS = ['html', 'markdown'];

    public function __construct(private readonly SafeContentRenderer $renderer) {}

    /** @return array{format: string, content: string}|null */
    public function source(mixed $value, int $limit): ?array
    {
        if (! is_array($value) || array_diff(array_keys($value), ['format', 'content']) !== []
            || ! in_array($value['format'] ?? null, self::FORMATS, true) || ! array_key_exists('content', $value) || ($value['content'] !== null && ! is_string($value['content']))
            || ! mb_check_encoding($value['content'] ?? '', 'UTF-8') || mb_strlen($value['content'] ?? '', 'UTF-8') > $limit) {
            return null;
        }

        return ['format' => $value['format'], 'content' => $value['content'] ?? ''];
    }

    /** @param array{format: string, content: string} $source */
    public function render(array $source): string
    {
        return $this->renderer->richText($source['content'], $source['format']);
    }

    /**
     * @param  array{format: string, content: string}  $source
     * @return array{format: string, content: string}
     */
    public function convert(array $source, string $format): array
    {
        if ($format === $source['format']) {
            return $source;
        }
        $html = $this->render($source);
        if ($format === 'html') {
            return ['format' => 'html', 'content' => $html];
        }
        $converter = new HtmlConverter(['header_style' => 'atx']);
        $converter->getEnvironment()->addConverter(new TableConverter);
        $converted = ['format' => 'markdown', 'content' => $converter->convert($html)];
        if ($this->signature($html) !== $this->signature($this->render($converted))) {
            throw ValidationException::withMessages(['target_format' => __('admin.rich_text.conversion_loss')]);
        }

        return $converted;
    }

    private function signature(string $html): string|false
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//body/text() | //ul/text() | //ol/text() | //li/text() | //blockquote/text() | //table/text() | //thead/text() | //tbody/text() | //tr/text()') ?: [] as $node) {
            if ($node instanceof DOMText && trim($node->data) === '' && (str_contains($node->data, "\n") || $node->previousSibling === null || $node->nextSibling === null)) {
                $node->parentNode?->removeChild($node);
            }
        }

        return $body instanceof DOMElement ? $body->C14N() : false;
    }
}

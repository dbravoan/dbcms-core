<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\SafeContentRenderer;
use DbCms\Core\Services\Media\MarkdownRendererService;

class RichTextBlock extends AbstractBlockType
{
    protected MarkdownRendererService $markdown;

    public function __construct(?MarkdownRendererService $markdown = null)
    {
        $this->markdown = $markdown ?? app(MarkdownRendererService::class);
    }

    public function getTranslationPaths(): array
    {
        return ['title', 'content', 'format'];
    }

    public function getType(): string
    {
        return 'rich_text';
    }

    public function getName(): string
    {
        return 'Rich Text';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-file-lines';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Bloque de texto formateado con soporte para negrita, encabezados, listas, citas, código e imágenes.';
    }

    public function getEditorView(): ?string
    {
        return 'basic::editor.rich-text';
    }

    public function getEditorSchema(): array
    {
        $fields = parent::getEditorSchema();
        $fields['format'] = [
            'type' => 'select',
            'label' => 'Formato del contenido',
            'options' => ['markdown' => 'Markdown', 'html' => 'HTML saneado'],
            'default' => 'html',
            'description' => 'El editor muestra las mismas opciones en su barra de herramientas.',
        ];

        return $fields;
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Sección de Contenido Técnico',
            'content' => "## Rendimiento y Escalabilidad Perimetral\n\nNuestra arquitectura garantiza una latencia **sub-50ms** en todo el mundo, integrando microservicios modulares y sincronización multi-mercado.",
            'format' => 'markdown',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Technical content section',
            'content' => "## Edge performance and scalability\n\nOur architecture keeps latency **under 50 ms** worldwide, combining modular microservices with multi-market synchronisation.",
        ]);
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $rawContent = (string) ($payload['content'] ?? '');
        $format = (string) ($payload['format'] ?? 'markdown');

        $renderedHtml = app(SafeContentRenderer::class)->richText($rawContent, $format);
        $renderedHtml = $this->markdown->resolveShortcodes($renderedHtml);

        $titleHtml = ! empty($title) ? "<h2 class=\"text-2xl font-bold cms-text mb-6 flex items-center gap-2\"><span class=\"cms-accent\">&gt;</span> {$title}</h2>" : '';

        return <<<HTML
        <div class="cms-rich-text prose prose-invert max-w-none font-mono text-xs sm:text-sm cms-text-muted leading-relaxed">
            {$titleHtml}
            {$renderedHtml}
        </div>
        HTML;
    }
}

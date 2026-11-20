<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;

class CodeEmbedBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function getType(): string
    {
        return 'code_embed';
    }

    public function getName(): string
    {
        return 'Incrustador de Código / HTML';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-code';
    }

    public function getCategory(): string
    {
        return 'interactive';
    }

    public function getDescription(): string
    {
        return 'Incrustación de fragmentos HTML, iframes, widgets de terceros o scripts seguros.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Widget incrustado',
            'external_service' => '',
            'content' => '<div class="p-4 cms-surface-2 border cms-border rounded-lg text-center font-mono text-xs cms-text-muted">Contenido HTML incrustado</div>',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Embedded widget',
            'content' => '<div class="p-4 cms-surface-2 border cms-border rounded-lg text-center font-mono text-xs cms-text-muted">Embedded HTML content</div>',
        ]);
    }

    public function getEditorSchema(): array
    {
        $services = app(ExternalContentPolicy::class)->services();

        return [
            'external_service' => ['type' => 'select', 'label' => 'Servicio externo declarado', 'options' => array_map(static fn (array $service): string => $service['provider'], $services), 'rules' => ['nullable', 'string']],
            'title' => ['type' => 'text', 'label' => 'Título opcional', 'description' => 'Etiqueta pequeña sobre el contenido incrustado.', 'rules' => ['nullable', 'string', 'max:255']],
            'content' => ['type' => 'textarea', 'label' => 'HTML incrustado', 'description' => 'Se renderiza dentro de un iframe con sandbox. Revisa el código antes de publicar.', 'rules' => ['nullable', 'string']],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $title = ! empty($payload['title']) ? htmlspecialchars((string) $payload['title']) : '';
        $policy = app(ExternalContentPolicy::class);
        $service = (string) ($payload['external_service'] ?? '');
        $rawCode = htmlspecialchars($policy->document((string) ($payload['content'] ?? ''), $service), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $header = ! empty($title) ? "<div class=\"text-xs text-gray-500 font-bold uppercase mb-2 font-mono\">{$title}</div>" : '';

        $html = <<<HTML
        <div class="w-full overflow-hidden my-4">
            {$header}
            <iframe class="w-full min-h-80 border-0" title="{$title}" sandbox="allow-scripts" referrerpolicy="no-referrer" srcdoc="{$rawCode}"></iframe>
        </div>
        HTML;

        return $policy->render($html, $service);
    }
}

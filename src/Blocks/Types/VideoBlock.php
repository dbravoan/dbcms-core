<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;

class VideoBlock extends AbstractBlockType
{
    public function getTranslationPaths(): array
    {
        return ['title', 'caption'];
    }

    public function getType(): string
    {
        return 'video';
    }

    public function getName(): string
    {
        return 'Vídeo Cinemático / Reproductor';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-video';
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function getDescription(): string
    {
        return 'Reproductor de vídeo HTML5 responsivo (16:9) con imagen póster, subtítulos accesibles y controles personalizables.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'video_url' => '',
            'external_service' => '',
            'poster_url' => '/images/placeholder.svg',
            'title' => 'Recorrido por el producto',
            'caption' => 'Visita de dos minutos por el panel de edición: secciones, bloques y publicación.',
            'autoplay' => false,
            'loop' => false,
            'controls' => true,
            'muted' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), [
            'title' => 'Product walkthrough',
            'caption' => 'A two-minute tour of the editing panel: sections, blocks and publishing.',
        ]);
    }

    public function getEditorSchema(): array
    {
        $schema = parent::getEditorSchema();
        $services = app(ExternalContentPolicy::class)->services();
        $schema['external_service'] = ['type' => 'select', 'label' => 'Servicio externo declarado', 'options' => ['' => 'Archivo local'] + array_map(static fn (array $service): string => $service['provider'], $services), 'rules' => ['nullable', 'string']];

        return $schema;
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $videoUrl = htmlspecialchars((string) ($payload['video_url'] ?? ''));
        $posterUrl = htmlspecialchars((string) ($payload['poster_url'] ?? ''));
        $title = htmlspecialchars((string) ($payload['title'] ?? ''));
        $caption = htmlspecialchars((string) ($payload['caption'] ?? ''));
        $autoplay = ! empty($payload['autoplay']) ? 'autoplay' : '';
        $loop = ! empty($payload['loop']) ? 'loop' : '';
        $controls = ($payload['controls'] ?? true) ? 'controls' : '';
        $muted = (! empty($payload['muted']) || ! empty($autoplay)) ? 'muted playsinline' : 'playsinline';

        if (empty($videoUrl)) {
            return <<<'HTML'
            <div class="w-full aspect-video rounded-2xl bg-black/40 border cms-border border-dashed flex flex-col items-center justify-center p-8 text-center">
                <i class="fa-solid fa-film text-3xl cms-text-muted mb-3 opacity-40"></i>
                <p class="text-sm font-bold cms-text">Vídeo no configurado</p>
                <p class="text-xs cms-text-muted mt-1">Configura una URL de vídeo o genera un clip cinemático con IA.</p>
            </div>
            HTML;
        }

        $posterAttr = ! empty($posterUrl) ? "poster=\"{$posterUrl}\"" : '';
        $headerHtml = ! empty($title) ? "<h3 class=\"text-xl font-bold cms-text mb-2\">{$title}</h3>" : '';
        $captionHtml = ! empty($caption) ? "<p class=\"text-xs sm:text-sm cms-text-muted mt-3 font-mono text-center\">{$caption}</p>" : '';

        $html = <<<HTML
        <div class="w-full my-6">
            {$headerHtml}
            <div class="relative w-full aspect-video rounded-2xl overflow-hidden border cms-border shadow-2xl bg-black">
                <video src="{$videoUrl}" {$posterAttr} {$controls} {$autoplay} {$loop} {$muted} class="w-full h-full object-cover">
                    Tu navegador no soporta la reproducción de vídeo HTML5.
                </video>
            </div>
            {$captionHtml}
        </div>
        HTML;
        foreach (['video_url', 'poster_url'] as $key) {
            $url = (string) ($payload[$key] ?? '');
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0) {
                $policy = app(ExternalContentPolicy::class);
                $service = (string) ($payload['external_service'] ?? '');
                foreach (['video_url', 'poster_url'] as $remoteKey) {
                    $remote = (string) ($payload[$remoteKey] ?? '');
                    $remoteHost = parse_url($remote, PHP_URL_HOST);
                    if (is_string($remoteHost) && strcasecmp($remoteHost, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0 && ! $policy->permits($service, $remote)) {
                        return $policy->render('', '');
                    }
                }

                return $policy->render($html, $service);
            }
        }

        return $html;
    }
}

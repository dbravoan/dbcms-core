<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class PlaylistBlock extends ComposableBlock
{
    protected const TYPE = 'playlist';

    protected const NAME = 'Lista multimedia';

    protected const ICON = 'fa-solid fa-circle-play';

    protected const CATEGORY = 'media';

    protected const DEFAULTS = ['title' => '', 'kind' => 'audio', 'external_service' => '', 'order' => 'authored', 'repeat' => 'none', 'auto_advance' => true, 'items' => []];

    protected const EXAMPLE = ['title' => 'Pódcast «Webs que venden»', 'items' => [
        ['title' => 'Episodio 1 · Cómo elegir el dominio perfecto'],
        ['title' => 'Episodio 2 · Textos que convierten'],
        ['title' => 'Episodio 3 · SEO local para negocios de barrio'],
    ]];

    protected const EXAMPLE_EN = ['title' => 'Podcast "Websites that sell"', 'items' => [
        ['title' => 'Episode 1 · Choosing the perfect domain'],
        ['title' => 'Episode 2 · Copy that converts'],
        ['title' => 'Episode 3 · Local SEO for neighbourhood businesses'],
    ]];

    protected const TRANSLATABLE = ['title', 'items.*.title', 'items.*.subtitle_label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['title' => self::text('Título'), 'kind' => self::select('Tipo', ['audio' => 'Audio', 'video' => 'Vídeo']), 'external_service' => self::externalService(), 'order' => self::select('Orden de reproducción', ['authored' => 'Orden de la lista', 'reverse' => 'Orden inverso', 'shuffle' => 'Aleatorio por visita']), 'repeat' => self::select('Repetición', ['none' => 'No repetir', 'all' => 'Repetir la lista', 'one' => 'Repetir la pista']), 'auto_advance' => self::boolean('Continuar al terminar una pista iniciada por el visitante'), 'items' => self::items(['title' => self::text('Título de la pista'), 'media_url' => self::media('Archivo de audio', 'audio'), 'video_url' => self::media('Archivo de vídeo', 'video'), 'poster_url' => self::media('Póster del vídeo'), 'subtitle_url' => array_replace(self::text('Subtítulos WebVTT', 2048, 'url'), ['asset' => true, 'rules' => ['nullable', 'string', 'max:2048', 'regex:~^(?:/[^/]|https?://)~']]), 'subtitle_language' => self::text('Idioma de los subtítulos (BCP 47)', 35), 'subtitle_label' => self::text('Nombre de los subtítulos', 80)], 'Pistas')]);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class MapBlock extends ComposableBlock
{
    protected const TYPE = 'map';

    protected const NAME = 'Mapa';

    protected const ICON = 'fa-solid fa-map-location-dot';

    protected const CATEGORY = 'media';

    protected const DEFAULTS = ['title' => '', 'address' => '', 'embed_url' => '', 'external_service' => '', 'directions_url' => ''];

    protected const EXAMPLE = ['title' => 'Nuestra oficina en Madrid', 'address' => 'Calle de Alcalá 48, 2.º izquierda, 28014 Madrid', 'directions_url' => 'https://www.openstreetmap.org/search?query=Calle%20de%20Alcal%C3%A1%2048%2C%20Madrid'];

    protected const EXAMPLE_EN = ['title' => 'Our office in Madrid', 'address' => 'Calle de Alcalá 48, 2nd floor left, 28014 Madrid, Spain', 'directions_url' => 'https://www.openstreetmap.org/search?query=Calle%20de%20Alcal%C3%A1%2048%2C%20Madrid'];

    protected const TRANSLATABLE = ['title', 'address'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['title' => self::text('Título accesible'), 'address' => self::text('Dirección visible', 2000, 'textarea'), 'embed_url' => self::text('URL de inserción declarada', 2048, 'url'), 'external_service' => self::externalService(), 'directions_url' => self::text('Enlace para indicaciones', 2048, 'url')]);
    }
}

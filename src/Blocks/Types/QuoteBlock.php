<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class QuoteBlock extends ComposableBlock
{
    protected const TYPE = 'quote';

    protected const NAME = 'Cita';

    protected const ICON = 'fa-solid fa-quote-left';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['quote' => '', 'author' => '', 'source_label' => '', 'source_url' => ''];

    protected const EXAMPLE = ['quote' => 'En tres meses duplicamos las reservas online y ahora actualizamos la web nosotros mismos, sin depender de nadie.', 'author' => 'Lucía Ferrer, directora de Clínica Aurora', 'source_label' => 'Caso de éxito'];

    protected const EXAMPLE_EN = ['quote' => 'In three months we doubled our online bookings, and now we update the website ourselves without relying on anyone.', 'author' => 'Lucía Ferrer, director of Aurora Clinic', 'source_label' => 'Case study'];

    protected const TRANSLATABLE = ['quote', 'author', 'source_label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['quote' => self::text('Cita', 10000, 'textarea'), 'author' => self::text('Autor'), 'source_label' => self::text('Texto de la fuente'), 'source_url' => self::text('Fuente', 2048, 'url')]);
    }
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class PriceBlock extends ComposableBlock
{
    protected const TYPE = 'price';

    protected const NAME = 'Precio';

    protected const ICON = 'fa-solid fa-tag';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['amount' => '', 'currency' => '', 'period' => '', 'previous_amount' => '', 'note' => ''];

    protected const EXAMPLE = ['amount' => '49', 'currency' => '€', 'period' => 'al mes, IVA incluido', 'previous_amount' => '59', 'note' => 'Sin permanencia. Primer mes gratis al contratar el plan anual.'];

    protected const EXAMPLE_EN = ['amount' => '49', 'currency' => '€', 'period' => 'per month, VAT included', 'previous_amount' => '59', 'note' => 'No lock-in. First month free with the annual plan.'];

    protected const TRANSLATABLE = ['period', 'note'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['amount' => self::text('Importe visible', 80), 'currency' => self::text('Moneda', 20), 'period' => self::text('Periodo', 120), 'previous_amount' => self::text('Precio anterior', 80), 'note' => self::text('Condiciones', 2000, 'textarea')]);
    }
}

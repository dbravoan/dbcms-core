<?php

declare(strict_types=1);

use DbCms\Core\Forms\Models\CustomForm;

return [
    'forms' => [
        'permission' => 'manage:forms',
        'module' => null,
        'model' => CustomForm::class,
        'value' => ['id'],
        'allowed_values' => ['id', 'slug'],
        'label' => ['translation.name', 'slug'],
        'search' => ['translation.name', 'slug'],
        'market_scoped' => true,
        'order' => 'slug',
        'icon' => 'fa-solid fa-clipboard-list',
        'badge' => 'FORM',
    ],
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Form field framework
|--------------------------------------------------------------------------
| Declares every field type key the CMS knows about, even when the module that
| implements it is disabled. Consumers (renderer, validator, form builder) read
| this catalog so a disabled module's field type is explicitly ignored instead
| of silently degrading to a plain text input.
|
| `null` = provided by the core; a string = the module identifier that owns it.
*/

return [
    'types' => [
        // Core primitive HTML controls.
        'text' => null,
        'email' => null,
        'tel' => null,
        'number' => null,
        'textarea' => null,
        'select' => null,
        'checkbox' => null,
        'radio' => null,

        // Module-provided types (registered by the module when it is active).
        'captcha' => 'captcha',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field type labels (backoffice form builder)
    |--------------------------------------------------------------------------
    | Translation keys (or literal labels) for each type, plus the quick-add
    | button caption. Types without a label fall back to their machine key.
    */
    'labels' => [
        'text' => 'admin.forms_ui.type_text',
        'email' => 'admin.forms_ui.type_email',
        'tel' => 'admin.forms_ui.type_tel',
        'number' => 'admin.forms_ui.type_number',
        'textarea' => 'admin.forms_ui.type_textarea',
        'select' => 'admin.forms_ui.type_select',
        'checkbox' => 'admin.forms_ui.type_checkbox',
        'radio' => 'admin.forms_ui.type_radio',
        'captcha' => 'admin.forms_ui.type_captcha',
    ],

    'add_labels' => [
        'text' => 'admin.forms_ui.add_text',
        'email' => 'admin.forms_ui.add_email',
        'tel' => 'admin.forms_ui.add_tel',
        'textarea' => 'admin.forms_ui.add_textarea',
        'select' => 'admin.forms_ui.add_select',
        'checkbox' => 'admin.forms_ui.add_checkbox',
        'captcha' => 'admin.forms_ui.add_captcha',
    ],
];

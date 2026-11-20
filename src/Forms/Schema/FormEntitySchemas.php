<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Schema;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormEditorialPreview;
use DbCms\Core\Forms\Services\FormMutationHandler;

/**
 * Declares the Forms entities a generic consumer may operate on.
 */
final class FormEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::form()];
    }

    private static function form(): EntitySchema
    {
        return new EntitySchema(
            type: 'form',
            label: 'Formulario',
            model: CustomForm::class,
            description: 'Formulario localizado. attributes.fields acepta una lista de field_key, type, is_required, validation_rules y translations[locale] con label, placeholder y options. La lectura expone relations.fields. Omitir fields conserva la estructura; [] la vacía.',
            permission: 'manage:forms',
            createView: 'forms::admin.create',
            editView: 'forms::admin.edit',
            previewHandler: FormEditorialPreview::class,
            mutationHandler: FormMutationHandler::class,
            fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][name]', 'label' => 'Nombre', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'slug', 'label' => 'Slug'],
                EntitySchema::ROLE_BODY => ['input' => 'translations[{locale}][description]', 'label' => 'Descripción', 'multiline' => true],
                'submit_button_text' => ['input' => 'translations[{locale}][submit_button_text]', 'label' => 'Texto del botón de envío'],
                'success_message' => ['input' => 'translations[{locale}][success_message]', 'label' => 'Mensaje de éxito', 'multiline' => true],
                'layout' => ['input' => 'layout', 'label' => 'Pasos y grupos (referencias field_key, labels por idioma, columns mobile/tablet/desktop)', 'multiline' => true],
                'actions' => ['input' => 'actions', 'label' => 'Acciones tras envío: id, type confirm/redirect/email/webhook/open_popup/close_popup y target', 'multiline' => true],
                'fields' => ['input' => 'fields', 'label' => 'Campos estructurados', 'multiline' => true],
            ],
        );
    }
}

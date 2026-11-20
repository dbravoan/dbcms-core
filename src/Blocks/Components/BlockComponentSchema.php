<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components;

use DbCms\Core\Blocks\Components\Services\ComponentEditorialPreview;
use DbCms\Core\Blocks\Components\Services\ComponentMutationHandler;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Models\BlockComponent;

final class BlockComponentSchema
{
    public static function definition(): EntitySchema
    {
        return new EntitySchema(
            type: 'block_component', label: 'Plantilla', model: BlockComponent::class, sectionable: true,
            permission: 'manage:pages', searchable: false, mutationHandler: ComponentMutationHandler::class,
            previewHandler: ComponentEditorialPreview::class,
            description: 'Composición de secciones y bloques que se copia al insertarla en una página.',
            createView: 'admin.block-components.create', editView: 'admin.block-components.edit', adminFormView: 'admin.block-components._form',
            creationDefaults: ['exposed_properties' => []], fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Nombre', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Identificador'],
            ],
        );
    }
}

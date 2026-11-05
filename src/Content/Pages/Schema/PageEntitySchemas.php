<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Schema;

use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Services\PageEditorialPreview;
use DbCms\Core\Content\Pages\Services\PageMutationHandler;
use DbCms\Core\Content\PublicContentDefinition;

/**
 * Declares the Pages entities a generic consumer may operate on.
 */
final class PageEntitySchemas
{
    /**
     * @return list<EntitySchema>
     */
    public static function all(): array
    {
        return [self::page()];
    }

    private static function page(): EntitySchema
    {
        return new EntitySchema(
            type: 'page',
            publicContent: new PublicContentDefinition('pages', priority: '0.8'),
            label: 'Página',
            model: Page::class,
            sectionable: true,
            mutationHandler: PageMutationHandler::class,
            previewHandler: PageEditorialPreview::class,
            description: 'Página con bloques o una vista Blade registrada, con metadatos SEO. Las vistas Blade conservan los bloques sin ejecutarlos ni permitir su edición.',
            permission: 'manage:pages',
            urlPrefix: '',
            createView: 'admin.pages.create',
            adminFormView: 'admin.pages._form',
            editView: 'admin.pages.edit',
            fields: [
                'template' => ['input' => 'template', 'label' => 'Plantilla de página'],
                'schema_type' => ['input' => 'translations[{locale}][schema_type]', 'label' => 'Tipo de schema'],
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Título', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Slug'],
                EntitySchema::ROLE_META_TITLE => ['input' => 'translations[{locale}][meta_title]', 'label' => 'Meta título'],
                EntitySchema::ROLE_META_DESCRIPTION => ['input' => 'translations[{locale}][meta_description]', 'label' => 'Meta descripción', 'multiline' => true],
            ],
        );
    }
}

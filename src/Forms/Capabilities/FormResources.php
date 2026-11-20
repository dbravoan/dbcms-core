<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Capabilities;

use DbCms\Core\Capabilities\Resources\CapabilityResource;
use DbCms\Core\Forms\Models\FormSubmission;

final class FormResources
{
    public static function descriptor(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'form_submission',
            label: 'Envío de formulario',
            model: FormSubmission::class,
            permission: 'manage:forms',
            description: 'Bandeja de envíos de formularios: marcar revisados y eliminar.',
            fillable: ['is_reviewed'],
            rules: [
                'is_reviewed' => ['nullable', 'boolean'],
            ],
            searchable: ['locale'],
            columns: ['id', 'custom_form_id', 'locale', 'is_reviewed', 'ip_address', 'created_at'],
            allowCreate: false,
            orderBy: 'id',
            orderDirection: 'desc',
        );
    }
}

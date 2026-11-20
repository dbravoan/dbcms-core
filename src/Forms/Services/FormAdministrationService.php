<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\DTOs\Content\EntityMutationData;
use DbCms\Core\Forms\Contracts\FormAdministrationServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;

/** The HTTP editor and native executor share a single transactional writer. */
final class FormAdministrationService implements FormAdministrationServiceInterface
{
    public function __construct(private readonly EntityMutationService $entities, private readonly EntityReadService $reader) {}

    public function create(array $input, ?User $actor): CustomForm
    {
        $result = $this->entities->create('form', EntityMutationData::fromForm($input)->arguments, $actor);
        $form = $this->reader->resolveEntity('form', $result['id']);
        assert($form instanceof CustomForm);

        return $form;
    }

    public function update(CustomForm $form, array $input): array
    {
        $args = EntityMutationData::fromForm($input)->arguments;
        if ($form->is_active && ! empty($args['attributes']['is_active'])) {
            unset($args['attributes']['is_active']);
        }

        return $this->entities->update('form', $form->getKey(), $args);
    }

    public function delete(CustomForm $form): void
    {
        $this->entities->delete('form', $form->getKey());
    }
}

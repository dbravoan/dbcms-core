<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Contracts;

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Models\User;

interface FormAdministrationServiceInterface
{
    /** @param array<string,mixed> $input */
    public function create(array $input, ?User $actor): CustomForm;

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(CustomForm $form, array $input): array;

    public function delete(CustomForm $form): void;
}

<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Contracts;

use DbCms\Core\Forms\DTOs\FormEditorData;
use DbCms\Core\Forms\Models\CustomForm;

interface FormEditorServiceInterface
{
    public function creation(): FormEditorData;

    public function editing(CustomForm $form): FormEditorData;
}

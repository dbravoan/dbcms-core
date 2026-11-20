<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin;

use DbCms\Core\Forms\Contracts\FormAdministrationServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class FormDeleteController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(CustomForm $form): RedirectResponse
    {
        $this->service->delete($form);

        return redirect()->route('admin.forms.index')->with('success', 'Formulario y sus envíos eliminados.');
    }
}

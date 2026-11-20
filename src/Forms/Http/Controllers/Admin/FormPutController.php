<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin;

use DbCms\Core\Forms\Contracts\FormAdministrationServiceInterface;
use DbCms\Core\Forms\Http\Requests\FormMutationRequest;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class FormPutController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(FormMutationRequest $request, CustomForm $form): RedirectResponse
    {
        $this->service->update($form, $request->validated());

        return redirect()->route('admin.forms.edit', $form)->with('success', 'Formulario guardado; los cambios publicados pendientes requieren revisión.');
    }
}

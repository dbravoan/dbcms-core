<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin;

use DbCms\Core\Forms\Contracts\FormAdministrationServiceInterface;
use DbCms\Core\Forms\Http\Requests\FormMutationRequest;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class FormPostController extends Controller
{
    public function __construct(private readonly FormAdministrationServiceInterface $service) {}

    public function __invoke(FormMutationRequest $request): RedirectResponse
    {
        $form = $this->service->create($request->validated(), $request->user());

        return redirect()->route('admin.forms.edit', $form)->with('success', "Formulario '{$form->name}' creado correctamente.");
    }
}

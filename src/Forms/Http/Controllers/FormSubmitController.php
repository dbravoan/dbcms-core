<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers;

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormActionService;
use DbCms\Core\Forms\Services\FormDefinitionService;
use DbCms\Core\Forms\Services\FormDomainService;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormSubmitController extends Controller
{
    public function submit(Request $request, FormDomainService $service, ?string $formSlug = null): JsonResponse|RedirectResponse
    {
        $routeSlug = $request->route('formSlug');
        $slug = is_string($routeSlug) ? $routeSlug : (string) $formSlug;

        /** @var Market|null $market */
        $market = app()->bound('current.market') ? app('current.market') : null;
        $locale = app()->getLocale();

        /** @var CustomForm $form */
        $form = $service->getFormBySlug($slug, $market) ?? abort(404);

        // The captcha (if the form declares the field) is validated by the
        // domain service through the form field framework.
        $submission = $service->submitForm($form, $request->all(), $market, $locale);

        $actions = app(FormActionService::class)->browserActions($submission);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $form->success_message ?? 'Thank you! Your submission has been received.',
                'submission_id' => $submission->id,
                'actions' => $actions,
            ]);
        }

        foreach ($actions as $action) {
            if ($action['type'] === 'redirect' && app(FormDefinitionService::class)->internalPath($action['target'] ?? '')) {
                return redirect($action['target'] ?? '/');
            }
        }

        return back()
            ->with('form_actions', $actions)
            ->with('form_submitted', ['id' => $form->id, 'slug' => $form->slug, 'submission_id' => $submission->id])
            ->with('form_success', $form->success_message ?? 'Thank you! Your submission has been received.')
            ->with('success', $form->success_message ?? 'Thank you! Your submission has been received.');
    }
}

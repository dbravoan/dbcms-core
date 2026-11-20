<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class FormSubmissionsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = FormSubmission::with('form', 'attachments', 'actions');

        $submissions = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['ip_address'],
            ['form_id' => 'custom_form_id'],
            'created_at',
            'desc'
        );

        foreach ($submissions->items() as $submission) {
            $submission->setAttribute('payload_summary', collect($submission->payload)->map(static fn (mixed $value, string $key): string => $key.': '.(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value))->implode(' | '));
        }
        $forms = CustomForm::all();

        return view('forms::admin.submissions', [
            'submissions' => $submissions,
            'forms' => $forms,
        ]);
    }
}

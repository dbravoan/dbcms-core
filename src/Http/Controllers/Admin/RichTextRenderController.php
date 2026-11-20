<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Content\RichTextContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RichTextRenderController extends Controller
{
    public function __invoke(Request $request, RichTextContent $editor): JsonResponse
    {
        $input = $request->validate(['source' => 'required|array', 'target_format' => 'sometimes|in:html,markdown']);
        $source = $editor->source($input['source'], 262144);
        if ($source === null) {
            throw ValidationException::withMessages(['source' => 'Provide a valid HTML or Markdown document.']);
        }
        if (isset($input['target_format'])) {
            $source = $editor->convert($source, $input['target_format']);
        }

        return response()->json(['html' => $editor->render($source), 'source' => $source])
            ->header('Cache-Control', 'private, no-store');
    }
}

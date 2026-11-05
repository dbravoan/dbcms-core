<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Tokens;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final class PersonalTokenPostController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subject_type' => ['required', 'string'],
            'subject_id' => ['required', 'integer'],
            'scopes' => ['nullable', 'array'],
        ]);

        $scopes = (array) ($validated['scopes'] ?? []);
        $subjectType = (string) $validated['subject_type'];
        $subjectId = (int) $validated['subject_id'];
        $name = (string) $validated['name'];

        if ($subjectType === 'users') {
            /** @var User $subject */
            $subject = User::findOrFail($subjectId);
            $tokenResult = $subject->createToken($name, $scopes);
        } else {
            $modelClass = (string) Config::get("auth.providers.{$subjectType}.model");
            if ($modelClass === '' || ! class_exists($modelClass)) {
                return back()->withErrors(['subject_type' => 'Proveedor de autenticación no válido.'])->withInput();
            }
            /** @var User $subject */
            $subject = $modelClass::findOrFail($subjectId);
            $tokenResult = $subject->createToken($name, $scopes);
        }

        return redirect()->route('admin.tokens.index')->with([
            'success' => "Personal Access Token '{$name}' generado correctamente.",
            'issued_token' => [
                'name' => $name,
                'token' => $tokenResult->accessToken,
            ],
        ]);
    }
}

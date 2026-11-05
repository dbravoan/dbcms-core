<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Privacy;

use DbCms\Core\Services\Cms\CookieConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ConsentController
{
    public function show(Request $request, CookieConsentService $consent): JsonResponse
    {
        return response()->json(['revision' => $consent->revision(), 'preferences' => $consent->getConsentPreferences($request), 'configured' => $consent->hasGivenConsent($request), 'csrf' => csrf_token()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, CookieConsentService $consent): JsonResponse
    {
        $data = $request->validate(['revision' => ['required', 'string', 'size:64'], 'preferences' => ['required', 'array'], 'preferences.*' => ['boolean']]);
        if (! hash_equals($consent->revision(), (string) $data['revision'])) {
            throw ValidationException::withMessages(['revision' => 'Privacy information changed. Reload your preferences.']);
        }
        /** @var array<string,bool> $preferences */
        $preferences = array_map(static fn (mixed $value): bool => $value === true || $value === 1 || $value === '1', (array) $data['preferences']);
        $state = $consent->record($preferences);

        return response()->json($state + ['configured' => true])->header('Cache-Control', 'private, no-store')
            ->cookie(CookieConsentService::COOKIE, $state['id'], CookieConsentService::DAYS * 1440, '/', null, $request->isSecure(), true, false, 'lax');
    }
}

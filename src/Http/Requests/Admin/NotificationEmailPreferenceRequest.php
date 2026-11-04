<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use DbCms\Core\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class NotificationEmailPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasPermission('access:admin');
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['enabled' => 'required|boolean', 'module' => 'nullable|string|max:64', 'user_id' => 'prohibited'];
    }

    public function actor(): User
    {
        $user = $this->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}

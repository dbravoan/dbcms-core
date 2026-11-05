<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Api;

use DbCms\Core\DTOs\Auth\LoginUserDTO;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
        ];
    }

    public function toDTO(): LoginUserDTO
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return LoginUserDTO::fromArray($validated);
    }
}

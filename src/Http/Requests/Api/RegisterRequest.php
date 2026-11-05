<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Api;

use DbCms\Core\DTOs\Auth\RegisterUserDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
        ];
    }

    public function toDTO(): RegisterUserDTO
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return RegisterUserDTO::fromArray($validated);
    }
}

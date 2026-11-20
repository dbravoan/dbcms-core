<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Admin;

use Illuminate\Support\Facades\Validator;

final readonly class DatatableQueryData
{
    /** @param array<string, mixed> $query */
    public function __construct(public array $query) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        Validator::make($input, [
            'search' => 'nullable|string|max:2000', 'sort_by' => 'nullable|string|max:128',
            'sort_dir' => 'nullable|string|max:10', 'per_page' => 'nullable|integer', 'page' => 'nullable|integer|min:1',
        ])->validate();

        return new self($input);
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }
}

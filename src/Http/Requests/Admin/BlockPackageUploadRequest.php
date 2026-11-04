<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use DbCms\Core\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class BlockPackageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('manage:blocks');
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return ['package_zip' => 'required|file|mimes:zip|max:51200'];
    }

    public function package(): UploadedFile
    {
        $file = $this->file('package_zip');
        if (! $file instanceof UploadedFile) {
            throw new \LogicException('A validated package is required.');
        }

        return $file;
    }
}

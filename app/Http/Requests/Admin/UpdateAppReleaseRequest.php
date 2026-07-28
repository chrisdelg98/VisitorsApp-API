<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Metadata-only update. The binary is immutable: shipping different bytes under
 * the same version_code would leave devices unable to tell builds apart, so a
 * new build means a new release row.
 */
class UpdateAppReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'                     => ['sometimes', Rule::in(['draft', 'published', 'deprecated'])],
            'version_name'               => ['sometimes', 'string', 'max:30'],
            'release_notes'              => ['sometimes', 'nullable', 'string', 'max:2000'],
            'min_supported_version_code' => ['sometimes', 'integer', 'min:0'],
            'is_critical'                => ['sometimes', 'boolean'],
        ];
    }
}

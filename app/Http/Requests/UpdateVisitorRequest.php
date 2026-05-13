<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'       => ['sometimes', 'required', 'string', 'max:100'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'document_type'   => ['sometimes', 'required', Rule::in(['DUI', 'PASSPORT', 'LICENSE', 'OTHER'])],
            'email'           => ['sometimes', 'nullable', 'email:rfc', 'max:150'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'company'         => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}

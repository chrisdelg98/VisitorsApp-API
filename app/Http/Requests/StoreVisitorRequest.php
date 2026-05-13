<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'document_type'   => ['required', Rule::in(['DUI', 'PASSPORT', 'LICENSE', 'OTHER'])],
            'email'           => ['nullable', 'email:rfc', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'company'         => ['nullable', 'string', 'max:150'],
        ];
    }
}

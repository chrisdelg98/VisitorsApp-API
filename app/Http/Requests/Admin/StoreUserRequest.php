<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:150'],
            'email'      => ['required', 'email', 'max:191', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'max:191'],
            'role'       => ['required', Rule::in(['admin', 'super_admin', 'country_manager', 'viewer'])],
            'country_id' => [
                Rule::requiredIf(fn () => in_array($this->input('role'), ['country_manager', 'viewer'])),
                'nullable',
                'uuid',
                'exists:countries,id',
            ],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
